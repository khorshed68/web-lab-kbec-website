Feedback feature
================

This repository now includes a minimal feedback backend and a UI to submit suggestions/complaints.

Quick start (development):

1. Install dependencies for the server:

```bash
cd server
npm install
```

2. Start the server with an admin password set as an environment variable:

Windows PowerShell:

```powershell
$env:ADMIN_PASS = 'your_admin_password'
node index.js
```

macOS / Linux:

```bash
ADMIN_PASS=your_admin_password node index.js
```

3. Open the site at http://localhost:3000/index.html and click the floating feedback button.

Admin page:
- Open http://localhost:3000/admin-feedback.html and enter the same admin password to view submissions.

Notes and security:
- Do NOT commit or share your ADMIN_PASS. Use environment variables in production.
- Uploaded files are stored in `data/uploads` and submissions in `data/submissions.json`.
