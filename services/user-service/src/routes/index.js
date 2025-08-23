const express = require('express');
const router = express.Router();
const userController = require('../controllers/user.controller');
const auth = require('../middleware/auth');

// Public
router.post('/register', userController.register);
router.post('/login', userController.login);

// Admin protected
router.get('/', auth(['ADMIN']), userController.listUsers);
router.patch('/:id/role', auth(['ADMIN']), userController.updateUserRole);
router.delete('/:id', auth(['ADMIN']), userController.deleteUser);

module.exports = router;