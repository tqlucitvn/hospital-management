const nodemailer = require('nodemailer');

let transporter;
function getTransporter() {
  if (transporter) return transporter;
  if (process.env.ENABLE_EMAIL !== 'true') return null;
  transporter = nodemailer.createTransport({
    host: process.env.SMTP_HOST || 'mailhog',
    port: parseInt(process.env.SMTP_PORT || '1025', 10),
    secure: process.env.SMTP_SECURE === 'true',
    // demo: no auth for Mailhog; if auth required add user/pass here
  });
  return transporter;
}

async function sendMail(subject, html) {
  const t = getTransporter();
  if (!t) return false;
  const to = process.env.NOTIFY_DEMO_TO || 'demo@hms.local';
  const from = process.env.SMTP_FROM || 'noreply@hms.local';
  try {
    await t.sendMail({ from, to, subject, html });
    return true;
  } catch (e) {
    console.error('[email] send fail', e.message);
    return false;
  }
}

module.exports = { sendMail };