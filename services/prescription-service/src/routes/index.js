const express = require('express');
const router = express.Router();
const c = require('../controllers/prescription.controller');
const auth = require('../middleware/auth');

router.post('/', auth(['DOCTOR', 'ADMIN']), c.create);
router.get('/', auth(['DOCTOR', 'NURSE', 'ADMIN']), c.list);
router.get('/:id', auth(['DOCTOR', 'NURSE', 'ADMIN']), c.getOne);
router.patch('/:id/status', auth(['DOCTOR', 'ADMIN']), c.updateStatus);

module.exports = router;