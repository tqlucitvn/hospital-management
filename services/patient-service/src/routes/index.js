const express = require('express');
const router = express.Router();
const patientController = require('../controllers/patient.controller');
const auth = require('../middleware/auth');

router.post('/', auth(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']), patientController.createPatient);
router.get('/', auth(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']), patientController.getAllPatients);
router.get('/:id', auth(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']), patientController.getPatientById);
router.put('/:id', auth(['ADMIN', 'DOCTOR', 'NURSE']), patientController.updatePatient);
router.delete('/:id', auth(['ADMIN']), patientController.deletePatient);

module.exports = router;