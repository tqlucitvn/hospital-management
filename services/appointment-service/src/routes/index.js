const express = require('express');
const router = express.Router();
const c = require('../controllers/appointment.controller');
const auth = require('../middleware/auth');

router.post('/', auth(['ADMIN', 'RECEPTIONIST']), c.create);
router.get('/', auth(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']), c.list);
router.patch('/:id/status', auth(['ADMIN', 'DOCTOR', 'RECEPTIONIST']), c.updateStatus);
router.delete('/:id', auth(['ADMIN']), c.delete);

module.exports = router;