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

const fsp = fs.promises;
const DATA_DIR = path.join(__dirname, '..', 'data');
const UPLOADS = path.join(DATA_DIR, 'uploads');
const SUBMISSIONS_FILE = path.join(DATA_DIR, 'submissions.json');
const MEMBERS_FILE = path.join(DATA_DIR, 'members.json');
const SESSION_COOKIE = 'kbec_member_session';
const AUTH_TTL_MS = 30 * 24 * 60 * 60 * 1000;
const VERIFY_TTL_MS = 48 * 60 * 60 * 1000;
const KUET_EMAIL_RE = /^[^\s@]+@kuet\.ac\.bd$/i;

async function ensureDataDirs() {
  await fsp.mkdir(DATA_DIR, { recursive: true });
  await fsp.mkdir(UPLOADS, { recursive: true });
  try { await fsp.access(SUBMISSIONS_FILE); } catch (error) { await fsp.writeFile(SUBMISSIONS_FILE, '[]', 'utf8'); }
  try { await fsp.access(MEMBERS_FILE); } catch (error) { await fsp.writeFile(MEMBERS_FILE, '[]', 'utf8'); }
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

  const port = process.env.PORT || 3000;
  app.listen(port, () => console.log(`KBEC server running on http://localhost:${port}`));

})();
