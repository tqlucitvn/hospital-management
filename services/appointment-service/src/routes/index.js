const express = require('express');
const router = express.Router();
const c = require('../controllers/appointment.controller');
const auth = require('../middleware/auth');
const { createAppointment, updateStatus } = require('../middleware/validate');

router.post('/', auth(['ADMIN', 'RECEPTIONIST']), createAppointment, c.create);
router.get('/', auth(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']), c.list);
router.get('/:id', auth(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']), c.getById);
router.put('/:id', auth(['ADMIN', 'RECEPTIONIST']), createAppointment, c.update);
router.patch('/:id/status', auth(['ADMIN', 'DOCTOR', 'RECEPTIONIST']), updateStatus, c.updateStatus);
router.delete('/:id', auth(['ADMIN']), c.delete);

module.exports = router;