const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const fsp = fs.promises;
const helmet = require('helmet');
const cors = require('cors');

const DATA_DIR = path.join(__dirname, '..', 'data');
const UPLOADS = path.join(DATA_DIR, 'uploads');
const SUBMISSIONS_FILE = path.join(DATA_DIR, 'submissions.json');

async function ensureDataDirs(){
  await fsp.mkdir(DATA_DIR, { recursive: true });
  await fsp.mkdir(UPLOADS, { recursive: true });
  try{ await fsp.access(SUBMISSIONS_FILE); }catch(e){ await fsp.writeFile(SUBMISSIONS_FILE, '[]', 'utf8'); }
}

function sanitize(str){ if(!str) return ''; return str.toString().replace(/[<>&"']/g, s=>({"<":"&lt;",">":"&gt;","&":"&amp;","\"":"&quot;","'":"&#39;"}[s])); }

(async ()=>{
  await ensureDataDirs();
  const app = express();
  app.use(helmet());
  app.use(cors());
  app.use(express.json());

  // serve site files
  app.use(express.static(path.join(__dirname, '..')));

  // Multer setup
  const storage = multer.diskStorage({
    destination: (req, file, cb)=> cb(null, UPLOADS),
    filename: (req, file, cb)=> {
      const safe = Date.now() + '-' + Math.random().toString(36).slice(2,9) + path.extname(file.originalname);
      cb(null, safe);
    }
  });

  const upload = multer({
    storage,
    limits: { fileSize: 3 * 1024 * 1024 },
    fileFilter: (req, file, cb)=>{
      const allowed = ['image/jpeg','image/png','application/pdf'];
      if(allowed.includes(file.mimetype)) cb(null, true); else cb(new Error('Invalid file type'));
    }
  });

  // simple in-memory rate limiter by IP
  const lastSubmission = new Map(); // ip -> timestamp

  app.post('/api/feedback', upload.single('attachment'), async (req, res)=>{
    try{
      const ip = req.ip || req.connection.remoteAddress || 'unknown';
      const now = Date.now();
      const last = lastSubmission.get(ip) || 0;
      if(now - last < 60*1000) return res.status(429).json({ message: 'Too many submissions. Please wait a minute.' });

      const { type, name, email, subject, message, consent } = req.body;
      if(!type || !['Suggestion','Complaint'].includes(type)) return res.status(400).json({ message: 'Invalid type' });
      if(!subject || subject.toString().trim().length === 0 || subject.toString().length > 120) return res.status(400).json({ message: 'Invalid subject' });
      if(!message || message.toString().trim().length < 10) return res.status(400).json({ message: 'Message too short' });
      if(!consent) return res.status(400).json({ message: 'Consent required' });

      // sanitize
      const entry = {
        id: Date.now() + '-' + Math.random().toString(36).slice(2,8),
        type: sanitize(type),
        name: sanitize(name),
        email: sanitize(email),
        subject: sanitize(subject),
        message: sanitize(message),
        attachment: req.file ? path.basename(req.file.path) : null,
        ip,
        createdAt: new Date().toISOString()
      };

      // append to submissions file
      const buf = await fsp.readFile(SUBMISSIONS_FILE, 'utf8');
      const arr = JSON.parse(buf || '[]');
      arr.unshift(entry);
      await fsp.writeFile(SUBMISSIONS_FILE, JSON.stringify(arr, null, 2), 'utf8');

      lastSubmission.set(ip, now);
      res.json({ ok: true, id: entry.id });
    }catch(err){ console.error(err); res.status(500).json({ message: err.message || 'Server error' }); }
  });

  // admin endpoint (requires ADMIN_PASS env header x-admin-pass)
  app.get('/api/admin/submissions', async (req, res)=>{
    const pass = process.env.ADMIN_PASS;
    const provided = req.headers['x-admin-pass'] || req.query.pass;
    if(!pass) return res.status(500).json({ message: 'Admin password not set on server (set ADMIN_PASS env).' });
    if(!provided || provided !== pass) return res.status(401).json({ message: 'Unauthorized' });
    try{
      const buf = await fsp.readFile(SUBMISSIONS_FILE, 'utf8');
      const arr = JSON.parse(buf || '[]');
      res.json(arr);
    }catch(err){ res.status(500).json({ message: 'Failed to read submissions' }); }
  });

  const port = process.env.PORT || 3000;
  app.listen(port, ()=> console.log(`Feedback server running on http://localhost:${port}`));

})();
