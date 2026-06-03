const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');
const helmet = require('helmet');
const cors = require('cors');

let nodemailer = null;
try {
  nodemailer = require('nodemailer');
} catch (error) {
  nodemailer = null;
}

let QRCode = null;
try {
  QRCode = require('qrcode');
} catch (error) {
  QRCode = null;
}

const fsp = fs.promises;
const DATA_DIR = path.join(__dirname, '..', 'data');
const UPLOADS = path.join(DATA_DIR, 'uploads');
const SUBMISSIONS_FILE = path.join(DATA_DIR, 'submissions.json');
const MEMBERS_FILE = path.join(DATA_DIR, 'members.json');
const EVENTS_FILE = path.join(DATA_DIR, 'events.json');
const EVENT_REGISTRATIONS_FILE = path.join(DATA_DIR, 'event-registrations.json');
const SESSION_COOKIE = 'kbec_member_session';
const AUTH_TTL_MS = 30 * 24 * 60 * 60 * 1000;
const VERIFY_TTL_MS = 48 * 60 * 60 * 1000;
const KUET_EMAIL_RE = /^[^\s@]+@kuet\.ac\.bd$/i;

const EVENT_SEED = [
  {
    id: 'nexus-case-challenge-2026',
    title: 'NEXUS National Case Challenge 2026',
    type: 'Case Competition',
    start: '2026-05-16',
    end: '2026-05-17',
    deadline: '2026-05-12',
    venue: 'KUET Auditorium',
    summary: 'Inter-university business case competition focused on strategy, analysis, and presentation.',
    capacity: 180
  },
  {
    id: 'innovate-tech-fest-2026',
    title: 'InnovateTech Fest 2026',
    type: 'Tech Fest',
    start: '2026-06-05',
    end: '2026-06-07',
    deadline: '2026-06-01',
    venue: 'KUET Campus',
    summary: 'A three-day festival of startup pitches, hackathons, product showcases, and founder talks.',
    capacity: 240
  },
  {
    id: 'tdexkuet-ideas-leadership-session',
    title: 'TDExKUET Ideas & Leadership Session',
    type: 'Talk',
    start: '2026-07-10',
    end: '2026-07-10',
    deadline: '2026-07-04',
    venue: 'ECE Building, Room 204',
    summary: 'A curated leadership session featuring inspiring speakers and practical ideas for students.',
    capacity: 120
  },
  {
    id: 'kbec-entrepreneurship-summit-2026',
    title: 'KBEC Entrepreneurship Summit 2026',
    type: 'Summit',
    start: '2026-08-20',
    end: '2026-08-21',
    deadline: '2026-08-13',
    venue: 'KUET Gymnasium',
    summary: 'Our flagship summit bringing together entrepreneurs, investors, and thought leaders.',
    capacity: 300
  }
];

async function ensureDataDirs() {
  await fsp.mkdir(DATA_DIR, { recursive: true });
  await fsp.mkdir(UPLOADS, { recursive: true });
  try { await fsp.access(SUBMISSIONS_FILE); } catch (error) { await fsp.writeFile(SUBMISSIONS_FILE, '[]', 'utf8'); }
  try { await fsp.access(MEMBERS_FILE); } catch (error) { await fsp.writeFile(MEMBERS_FILE, '[]', 'utf8'); }
  try { await fsp.access(EVENTS_FILE); } catch (error) { await fsp.writeFile(EVENTS_FILE, JSON.stringify(EVENT_SEED, null, 2), 'utf8'); }
  try { await fsp.access(EVENT_REGISTRATIONS_FILE); } catch (error) { await fsp.writeFile(EVENT_REGISTRATIONS_FILE, '[]', 'utf8'); }
}

function sanitize(str) {
  if (!str) return '';
  return str.toString().replace(/[<>&"']/g, character => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;', "'": '&#39;' }[character]));
}

function parseCookies(req) {
  const header = req.headers.cookie || '';
  return header.split(';').reduce((cookies, pair) => {
    const [rawKey, ...rawValue] = pair.trim().split('=');
    if (!rawKey) return cookies;
    cookies[decodeURIComponent(rawKey)] = decodeURIComponent(rawValue.join('=') || '');
    return cookies;
  }, {});
}

async function readJsonFile(filePath) {
  const content = await fsp.readFile(filePath, 'utf8');
  return JSON.parse(content || '[]');
}

async function writeJsonFile(filePath, value) {
  await fsp.writeFile(filePath, JSON.stringify(value, null, 2), 'utf8');
}

function hashPassword(password, salt = crypto.randomBytes(16).toString('hex')) {
  const hash = crypto.scryptSync(password, salt, 64).toString('hex');
  return { salt, hash };
}

function verifyPassword(member, password) {
  const hash = crypto.scryptSync(password, member.passwordSalt, 64).toString('hex');
  return crypto.timingSafeEqual(Buffer.from(hash, 'hex'), Buffer.from(member.passwordHash, 'hex'));
}

function generateToken(bytes = 32) {
  return crypto.randomBytes(bytes).toString('hex');
}

function normalizeEmail(email) {
  return (email || '').toString().trim().toLowerCase();
}

function normalizeText(value) {
  return (value || '').toString().trim();
}

function normalizePhone(value) {
  return (value || '').toString().trim().replace(/\s+/g, ' ');
}

function normalizeEventId(value) {
  return (value || '').toString().trim();
}

function toNoonDate(value) {
  const [year, month, day] = (value || '').split('-').map(Number);
  return new Date(year, month - 1, day, 12, 0, 0, 0);
}

function isBeforeDay(left, right) {
  const compareLeft = new Date(left.getFullYear(), left.getMonth(), left.getDate());
  const compareRight = new Date(right.getFullYear(), right.getMonth(), right.getDate());
  return compareLeft < compareRight;
}

function formatLongDate(date) {
  return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

function formatShortDate(date) {
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function isExpired(timestamp) {
  return !timestamp || Date.now() > Number(timestamp);
}

function publicMember(member) {
  return {
    id: member.id,
    memberCode: member.memberCode,
    name: member.name,
    studentId: member.studentId,
    email: member.email,
    department: member.department,
    batch: member.batch,
    phone: member.phone || '',
    interest: member.interest || '',
    bio: member.bio || '',
    verified: Boolean(member.verified),
    createdAt: member.createdAt,
    updatedAt: member.updatedAt
  };
}

function createSessionCookie() {
  return {
    httpOnly: true,
    sameSite: 'lax',
    path: '/',
    maxAge: AUTH_TTL_MS
  };
}

function clearSessionCookie() {
  return {
    httpOnly: true,
    sameSite: 'lax',
    path: '/',
    maxAge: 0
  };
}

async function loadMembers() {
  return readJsonFile(MEMBERS_FILE);
}

async function saveMembers(members) {
  await writeJsonFile(MEMBERS_FILE, members);
}

async function loadEvents() {
  const events = await readJsonFile(EVENTS_FILE);
  if (!Array.isArray(events) || events.length === 0) {
    await saveEvents(EVENT_SEED);
    return EVENT_SEED;
  }
  return events;
}

async function saveEvents(events) {
  await writeJsonFile(EVENTS_FILE, events);
}

async function loadEventRegistrations() {
  return readJsonFile(EVENT_REGISTRATIONS_FILE);
}

async function saveEventRegistrations(registrations) {
  await writeJsonFile(EVENT_REGISTRATIONS_FILE, registrations);
}

function getEventStatus(event, registrationCount, today = new Date()) {
  const deadline = toNoonDate(event.deadline);
  const start = toNoonDate(event.start);
  const capacity = Number(event.capacity || 0);
  const remainingSeats = Math.max(capacity - registrationCount, 0);
  const registrationClosed = isBeforeDay(deadline, today) || isBeforeDay(new Date(event.end), today);
  return {
    remainingSeats,
    registeredCount: registrationCount,
    isFull: remainingSeats <= 0,
    registrationClosed,
    status: remainingSeats <= 0 ? 'Full' : (registrationClosed ? 'Closed' : 'Open'),
    startsSoon: !isBeforeDay(start, today)
  };
}

function buildEventPublicPayload(event, registrations, today = new Date()) {
  const registrationCount = registrations.filter(entry => entry.eventId === event.id).length;
  return {
    ...event,
    startLabel: formatLongDate(toNoonDate(event.start)),
    endLabel: formatLongDate(toNoonDate(event.end)),
    deadlineLabel: formatLongDate(toNoonDate(event.deadline)),
    deadlineShort: formatShortDate(toNoonDate(event.deadline)),
    ...getEventStatus(event, registrationCount, today)
  };
}

async function createTicketQrDataUrl(baseUrl, ticketToken) {
  const checkInUrl = `${baseUrl}/api/events/check-in?ticket=${encodeURIComponent(ticketToken)}`;
  if (QRCode) {
    return QRCode.toDataURL(checkInUrl, {
      errorCorrectionLevel: 'M',
      margin: 1,
      width: 320,
      type: 'image/png'
    });
  }

  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320"><rect width="320" height="320" fill="white"/><rect x="24" y="24" width="272" height="272" rx="18" fill="#f5f8ff" stroke="#0066cc" stroke-width="6"/><text x="160" y="148" font-family="Arial" font-size="20" text-anchor="middle" fill="#0b2540">KBEC Ticket QR</text><text x="160" y="180" font-family="Arial" font-size="11" text-anchor="middle" fill="#52616b">${sanitize(checkInUrl)}</text></svg>`)}`;
}

function buildEventTicket(registration, event) {
  return {
    id: registration.id,
    ticketCode: registration.ticketCode,
    ticketToken: registration.ticketToken,
    eventId: event.id,
    title: event.title,
    type: event.type,
    venue: event.venue,
    start: event.start,
    end: event.end,
    deadline: event.deadline,
    memberName: registration.memberName,
    memberEmail: registration.memberEmail,
    memberPhone: registration.memberPhone,
    department: registration.department,
    batch: registration.batch,
    note: registration.note || '',
    registeredAt: registration.registeredAt,
    attendedAt: registration.attendedAt || null,
    qrDataUrl: registration.qrDataUrl,
    checkInUrl: registration.checkInUrl
  };
}

async function findCurrentMember(req) {
  const cookies = parseCookies(req);
  const token = cookies[SESSION_COOKIE];
  if (!token) return null;

  const members = await loadMembers();
  const member = members.find(entry => entry.authToken === token && entry.verified && !isExpired(entry.authExpiresAt));
  if (!member) return null;
  return { member, members };
}

function buildVerificationLink(req, token) {
  return `${req.protocol}://${req.get('host')}/?verify=${encodeURIComponent(token)}`;
}

function getRequestBaseUrl(req) {
  return `${req.protocol}://${req.get('host')}`;
}

function createMailer() {
  const host = process.env.SMTP_HOST;
  const user = process.env.SMTP_USER;
  const pass = process.env.SMTP_PASS;
  const from = process.env.SMTP_FROM || user;

  if (!nodemailer || !host || !user || !pass || !from) {
    return null;
  }

  return nodemailer.createTransport({
    host,
    port: Number(process.env.SMTP_PORT || 587),
    secure: String(process.env.SMTP_SECURE || '').toLowerCase() === 'true',
    auth: { user, pass }
  });
}

async function sendVerificationMessage(req, member) {
  const verificationLink = buildVerificationLink(req, member.verificationToken);
  const mailer = createMailer();
  if (!mailer) {
    return { verificationLink, delivered: false };
  }

  await mailer.sendMail({
    from: process.env.SMTP_FROM || process.env.SMTP_USER,
    to: member.email,
    subject: 'Verify your KBEC member account',
    text: `Hello ${member.name},\n\nVerify your KBEC account by opening this link:\n${verificationLink}\n\nThis link expires in 48 hours.`,
    html: `<p>Hello ${sanitize(member.name)},</p><p>Verify your KBEC account by opening this link:</p><p><a href="${verificationLink}">${verificationLink}</a></p><p>This link expires in 48 hours.</p>`
  });

  return { verificationLink, delivered: true };
}

(async () => {
  await ensureDataDirs();
  const app = express();
  app.use(helmet());
  app.use(cors());
  app.use(express.json({ limit: '1mb' }));

  app.use(express.static(path.join(__dirname, '..')));

  const storage = multer.diskStorage({
    destination: (req, file, cb) => cb(null, UPLOADS),
    filename: (req, file, cb) => {
      const safe = Date.now() + '-' + Math.random().toString(36).slice(2, 9) + path.extname(file.originalname);
      cb(null, safe);
    }
  });

  const upload = multer({
    storage,
    limits: { fileSize: 3 * 1024 * 1024 },
    fileFilter: (req, file, cb) => {
      const allowed = ['image/jpeg', 'image/png', 'application/pdf'];
      if (allowed.includes(file.mimetype)) cb(null, true); else cb(new Error('Invalid file type'));
    }
  });

  const lastSubmission = new Map();

  app.post('/api/feedback', upload.single('attachment'), async (req, res) => {
    try {
      const ip = req.ip || req.connection.remoteAddress || 'unknown';
      const now = Date.now();
      const last = lastSubmission.get(ip) || 0;
      if (now - last < 60 * 1000) return res.status(429).json({ message: 'Too many submissions. Please wait a minute.' });

      const { type, name, email, subject, message, consent } = req.body;
      if (!type || !['Suggestion', 'Complaint'].includes(type)) return res.status(400).json({ message: 'Invalid type' });
      if (!subject || subject.toString().trim().length === 0 || subject.toString().length > 120) return res.status(400).json({ message: 'Invalid subject' });
      if (!message || message.toString().trim().length < 10) return res.status(400).json({ message: 'Message too short' });
      if (!consent) return res.status(400).json({ message: 'Consent required' });

      const entry = {
        id: Date.now() + '-' + Math.random().toString(36).slice(2, 8),
        type: sanitize(type),
        name: sanitize(name),
        email: sanitize(email),
        subject: sanitize(subject),
        message: sanitize(message),
        attachment: req.file ? path.basename(req.file.path) : null,
        ip,
        createdAt: new Date().toISOString()
      };

      const submissions = await readJsonFile(SUBMISSIONS_FILE);
      submissions.unshift(entry);
      await writeJsonFile(SUBMISSIONS_FILE, submissions);

      lastSubmission.set(ip, now);
      res.json({ ok: true, id: entry.id });
    } catch (error) {
      console.error(error);
      res.status(500).json({ message: error.message || 'Server error' });
    }
  });

  app.get('/api/events', async (req, res) => {
    try {
      const [events, registrations] = await Promise.all([loadEvents(), loadEventRegistrations()]);
      const memberSession = await findCurrentMember(req);
      const payload = events.map(event => buildEventPublicPayload(event, registrations));
      const myTickets = memberSession
        ? registrations
            .filter(registration => registration.memberId === memberSession.member.id)
            .map(registration => {
              const event = events.find(item => item.id === registration.eventId);
              return event ? buildEventTicket(registration, event) : null;
            })
            .filter(Boolean)
        : [];

      res.json({ ok: true, events: payload, myTickets });
    } catch (error) {
      console.error(error);
      res.status(500).json({ message: error.message || 'Server error' });
    }
  });

  app.post('/api/events/register', async (req, res) => {
    try {
      const session = await findCurrentMember(req);
      if (!session) return res.status(401).json({ message: 'Please log in with a verified KUET member account first.' });

      const eventId = normalizeEventId(req.body.eventId || req.body.event);
      const note = normalizeText(req.body.note);
      if (!eventId) return res.status(400).json({ message: 'Select an event.' });

      const [events, registrations] = await Promise.all([loadEvents(), loadEventRegistrations()]);
      const event = events.find(item => item.id === eventId);
      if (!event) return res.status(404).json({ message: 'Event not found.' });

      const eventRegistrationCount = registrations.filter(entry => entry.eventId === event.id).length;
      const status = getEventStatus(event, eventRegistrationCount);
      if (status.registrationClosed) return res.status(410).json({ message: 'Registration for this event is closed.' });
      if (status.isFull) return res.status(409).json({ message: 'This event is full. Please choose another event.' });

      const existing = registrations.find(entry => entry.eventId === event.id && entry.memberId === session.member.id);
      if (existing) {
        return res.json({ ok: true, alreadyRegistered: true, ticket: buildEventTicket(existing, event), event: buildEventPublicPayload(event, registrations) });
      }

      const baseUrl = getRequestBaseUrl(req);
      const ticketToken = generateToken(20);
      const ticketCode = `KBEC-${event.id.split('-')[0].toUpperCase()}-${crypto.randomBytes(3).toString('hex').toUpperCase()}`;
      const checkInUrl = `${baseUrl}/api/events/check-in?ticket=${encodeURIComponent(ticketToken)}`;
      const qrDataUrl = await createTicketQrDataUrl(baseUrl, ticketToken);
      const now = new Date().toISOString();
      const registration = {
        id: generateToken(12),
        eventId: event.id,
        memberId: session.member.id,
        memberName: session.member.name,
        memberEmail: session.member.email,
        memberPhone: session.member.phone || '',
        department: session.member.department || '',
        batch: session.member.batch || '',
        note,
        ticketCode,
        ticketToken,
        qrDataUrl,
        checkInUrl,
        registeredAt: now,
        attendedAt: null
      };

      registrations.unshift(registration);
      await saveEventRegistrations(registrations);

      res.status(201).json({
        ok: true,
        message: 'Registration confirmed. Your ticket has been generated.',
        event: buildEventPublicPayload(event, registrations),
        ticket: buildEventTicket(registration, event)
      });
    } catch (error) {
      console.error(error);
      res.status(500).json({ message: error.message || 'Server error' });
    }
  });

  app.get('/api/events/my-tickets', async (req, res) => {
    try {
      const session = await findCurrentMember(req);
      if (!session) return res.status(401).json({ message: 'Please log in first.' });

      const [events, registrations] = await Promise.all([loadEvents(), loadEventRegistrations()]);
      const tickets = registrations
        .filter(entry => entry.memberId === session.member.id)
        .map(registration => {
          const event = events.find(item => item.id === registration.eventId);
          return event ? buildEventTicket(registration, event) : null;
        })
        .filter(Boolean)
        .sort((left, right) => new Date(right.registeredAt) - new Date(left.registeredAt));

      res.json({ ok: true, tickets });
    } catch (error) {
      console.error(error);
      res.status(500).json({ message: error.message || 'Server error' });
    }
  });

  async function markEventAttendance(req, res) {
    try {
      const ticketToken = normalizeText(req.query.ticket || req.body.ticket || req.body.ticketToken);
      if (!ticketToken) return res.status(400).json({ message: 'Ticket token is required.' });

      const [events, registrations] = await Promise.all([loadEvents(), loadEventRegistrations()]);
      const registration = registrations.find(entry => entry.ticketToken === ticketToken);
      if (!registration) return res.status(404).json({ message: 'Ticket not found.' });

      const event = events.find(item => item.id === registration.eventId);
      if (!event) return res.status(404).json({ message: 'Event not found.' });

      if (!registration.attendedAt) {
        registration.attendedAt = new Date().toISOString();
        await saveEventRegistrations(registrations);
      }

      res.json({ ok: true, message: 'Attendance recorded.', ticket: buildEventTicket(registration, event) });
    } catch (error) {
      console.error(error);
      res.status(500).json({ message: error.message || 'Server error' });
    }
  }

  app.get('/api/events/check-in', markEventAttendance);
  app.post('/api/events/check-in', express.json({ limit: '1mb' }), markEventAttendance);

  app.post('/api/member/register', async (req, res) => {
    try {
      const name = normalizeText(req.body.name);
      const studentId = normalizeText(req.body.studentId);
      const email = normalizeEmail(req.body.email);
      const department = normalizeText(req.body.department);
      const batch = normalizeText(req.body.batch);
      const phone = normalizePhone(req.body.phone);
      const interest = normalizeText(req.body.interest);
      const bio = normalizeText(req.body.bio);
      const password = (req.body.password || '').toString();

      if (!name || name.length < 2) return res.status(400).json({ message: 'Enter your full name.' });
      if (!studentId || studentId.length < 4) return res.status(400).json({ message: 'Enter a valid student ID.' });
      if (!KUET_EMAIL_RE.test(email)) return res.status(400).json({ message: 'Use your KUET email address ending in @kuet.ac.bd.' });
      if (!department) return res.status(400).json({ message: 'Department is required.' });
      if (!batch) return res.status(400).json({ message: 'Batch is required.' });
      if (!interest) return res.status(400).json({ message: 'Choose an interest area.' });
      if (!password || password.length < 8) return res.status(400).json({ message: 'Password must be at least 8 characters long.' });

      const members = await loadMembers();
      const emailTaken = members.find(member => member.email === email);
      const studentTaken = members.find(member => member.studentId === studentId && member.email !== email);

      if (studentTaken) {
        return res.status(409).json({ message: 'That student ID is already linked to another account.' });
      }

      if (emailTaken && emailTaken.verified) {
        return res.status(409).json({ message: 'An account with this KUET email already exists.' });
      }

      const { salt, hash } = hashPassword(password);
      const now = new Date().toISOString();
      const verificationToken = generateToken();
      const verificationExpiresAt = Date.now() + VERIFY_TTL_MS;
      const memberCode = emailTaken?.memberCode || `KBEC-${new Date().getFullYear()}-${crypto.randomBytes(3).toString('hex').toUpperCase()}`;

      const member = emailTaken || {
        id: generateToken(12),
        memberCode,
        createdAt: now,
        verified: false
      };

      member.name = name;
      member.studentId = studentId;
      member.email = email;
      member.department = department;
      member.batch = batch;
      member.phone = phone;
      member.interest = interest;
      member.bio = bio;
      member.passwordSalt = salt;
      member.passwordHash = hash;
      member.verified = false;
      member.verificationToken = verificationToken;
      member.verificationExpiresAt = verificationExpiresAt;
      member.authToken = null;
      member.authExpiresAt = null;
      member.updatedAt = now;
      if (!member.createdAt) member.createdAt = now;

      if (!emailTaken) {
        members.push(member);
      }

      await saveMembers(members);

      const verification = await sendVerificationMessage(req, member);
      res.status(201).json({
        ok: true,
        message: verification.delivered
          ? 'Account created. Check your KUET inbox for the verification link.'
          : 'Account created. Use the verification link below to confirm your KUET email.',
        verificationLink: verification.verificationLink,
        verificationDelivered: verification.delivered,
        member: publicMember(member)
      });
    } catch (error) {
      console.error(error);
      res.status(500).json({ message: error.message || 'Server error' });
    }
  });

  app.post('/api/member/login', async (req, res) => {
    try {
      const email = normalizeEmail(req.body.email);
      const password = (req.body.password || '').toString();

      if (!email || !password) return res.status(400).json({ message: 'Email and password are required.' });

      const members = await loadMembers();
      const member = members.find(entry => entry.email === email);

      if (!member) return res.status(401).json({ message: 'Invalid email or password.' });
      if (!member.verified) {
        const verificationToken = generateToken();
        member.verificationToken = verificationToken;
        member.verificationExpiresAt = Date.now() + VERIFY_TTL_MS;
        member.updatedAt = new Date().toISOString();
        await saveMembers(members);
        return res.status(403).json({
          message: 'Your KUET email is not verified yet. Open the verification link first.',
          verificationRequired: true,
          verificationLink: buildVerificationLink(req, verificationToken)
        });
      }

      if (!verifyPassword(member, password)) return res.status(401).json({ message: 'Invalid email or password.' });

      member.authToken = generateToken();
      member.authExpiresAt = Date.now() + AUTH_TTL_MS;
      member.updatedAt = new Date().toISOString();
      await saveMembers(members);

      res.cookie(SESSION_COOKIE, member.authToken, createSessionCookie());
      res.json({ ok: true, member: publicMember(member) });
    } catch (error) {
      console.error(error);
      res.status(500).json({ message: error.message || 'Server error' });
    }
  });

  app.get('/api/member/verify', async (req, res) => {
    try {
      const token = normalizeText(req.query.token);
      if (!token) return res.status(400).json({ message: 'Verification token is required.' });

      const members = await loadMembers();
      const member = members.find(entry => entry.verificationToken === token);

      if (!member) return res.status(404).json({ message: 'Verification token not found.' });
      if (member.verified) {
        if (!member.authToken || isExpired(member.authExpiresAt)) {
          member.authToken = generateToken();
          member.authExpiresAt = Date.now() + AUTH_TTL_MS;
        }
        member.updatedAt = new Date().toISOString();
        await saveMembers(members);
        res.cookie(SESSION_COOKIE, member.authToken, createSessionCookie());
        return res.json({ ok: true, member: publicMember(member), message: 'Your email is already verified.' });
      }

      if (isExpired(member.verificationExpiresAt)) {
        return res.status(410).json({ message: 'This verification link has expired. Please resend the verification email.' });
      }

      member.verified = true;
      member.verificationToken = null;
      member.verificationExpiresAt = null;
      member.authToken = generateToken();
      member.authExpiresAt = Date.now() + AUTH_TTL_MS;
      member.updatedAt = new Date().toISOString();
      await saveMembers(members);

      res.cookie(SESSION_COOKIE, member.authToken, createSessionCookie());
      res.json({ ok: true, member: publicMember(member), message: 'KUET email verified successfully.' });
    } catch (error) {
      console.error(error);
      res.status(500).json({ message: error.message || 'Server error' });
    }
  });

  app.post('/api/member/resend-verification', async (req, res) => {
    try {
      const email = normalizeEmail(req.body.email);
      if (!email) return res.status(400).json({ message: 'Email is required.' });

      const members = await loadMembers();
      const member = members.find(entry => entry.email === email);

      if (!member) return res.status(404).json({ message: 'No account found for this email.' });
      if (member.verified) return res.status(400).json({ message: 'This account is already verified.' });

      member.verificationToken = generateToken();
      member.verificationExpiresAt = Date.now() + VERIFY_TTL_MS;
      member.updatedAt = new Date().toISOString();
      await saveMembers(members);

      const verification = await sendVerificationMessage(req, member);
      res.json({
        ok: true,
        message: verification.delivered
          ? 'Verification email sent again.'
          : 'Verification link refreshed for local testing.',
        verificationLink: verification.verificationLink
      });
    } catch (error) {
      console.error(error);
      res.status(500).json({ message: error.message || 'Server error' });
    }
  });

  app.get('/api/member/me', async (req, res) => {
    try {
      const session = await findCurrentMember(req);
      if (!session) return res.status(401).json({ message: 'Not authenticated.' });
      res.json({ ok: true, member: publicMember(session.member) });
    } catch (error) {
      console.error(error);
      res.status(500).json({ message: error.message || 'Server error' });
    }
  });

  app.put('/api/member/profile', async (req, res) => {
    try {
      const session = await findCurrentMember(req);
      if (!session) return res.status(401).json({ message: 'Not authenticated.' });

      const { member, members } = session;
      const updates = {
        name: normalizeText(req.body.name),
        phone: normalizePhone(req.body.phone),
        department: normalizeText(req.body.department),
        batch: normalizeText(req.body.batch),
        interest: normalizeText(req.body.interest),
        bio: normalizeText(req.body.bio)
      };

      if (!updates.name || updates.name.length < 2) return res.status(400).json({ message: 'Full name is required.' });
      if (!updates.department) return res.status(400).json({ message: 'Department is required.' });
      if (!updates.batch) return res.status(400).json({ message: 'Batch is required.' });
      if (!updates.interest) return res.status(400).json({ message: 'Interest area is required.' });

      Object.assign(member, updates, { updatedAt: new Date().toISOString() });
      await saveMembers(members);

      res.json({ ok: true, member: publicMember(member), message: 'Profile updated successfully.' });
    } catch (error) {
      console.error(error);
      res.status(500).json({ message: error.message || 'Server error' });
    }
  });

  app.post('/api/member/logout', async (req, res) => {
    try {
      const session = await findCurrentMember(req);
      if (session) {
        session.member.authToken = null;
        session.member.authExpiresAt = null;
        session.member.updatedAt = new Date().toISOString();
        await saveMembers(session.members);
      }

      res.clearCookie(SESSION_COOKIE, clearSessionCookie());
      res.json({ ok: true, message: 'Logged out.' });
    } catch (error) {
      console.error(error);
      res.status(500).json({ message: error.message || 'Server error' });
    }
  });

  app.get('/api/admin/submissions', async (req, res) => {
    const pass = process.env.ADMIN_PASS;
    const provided = req.headers['x-admin-pass'] || req.query.pass;
    if (!pass) return res.status(500).json({ message: 'Admin password not set on server (set ADMIN_PASS env).' });
    if (!provided || provided !== pass) return res.status(401).json({ message: 'Unauthorized' });
    try {
      const submissions = await readJsonFile(SUBMISSIONS_FILE);
      res.json(submissions);
    } catch (error) {
      res.status(500).json({ message: 'Failed to read submissions' });
    }
  });

  app.get('/api/admin/event-registrations', async (req, res) => {
    const pass = process.env.ADMIN_PASS;
    const provided = req.headers['x-admin-pass'] || req.query.pass;
    if (!pass) return res.status(500).json({ message: 'Admin password not set on server (set ADMIN_PASS env).' });
    if (!provided || provided !== pass) return res.status(401).json({ message: 'Unauthorized' });

    try {
      const [events, registrations] = await Promise.all([loadEvents(), loadEventRegistrations()]);
      const payload = registrations.map(registration => {
        const event = events.find(item => item.id === registration.eventId);
        return {
          ...registration,
          eventTitle: event ? event.title : registration.eventId,
          eventType: event ? event.type : '',
          eventVenue: event ? event.venue : '',
          eventStart: event ? event.start : '',
          eventEnd: event ? event.end : ''
        };
      });
      res.json(payload);
    } catch (error) {
      res.status(500).json({ message: 'Failed to read event registrations' });
    }
  });

  const port = process.env.PORT || 3000;
  app.listen(port, () => console.log(`KBEC server running on http://localhost:${port}`));

})();
