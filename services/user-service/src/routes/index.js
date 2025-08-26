const express = require('express');
const router = express.Router();
const userController = require('../controllers/user.controller');
const auth = require('../middleware/auth');

// Add logging middleware
router.use((req, res, next) => {
    console.log(`${req.method} ${req.path} - ${new Date().toISOString()}`);
    next();
});

// Public
router.post('/register', userController.register);
router.post('/login', userController.login);

// Admin protected - specific routes first
router.patch('/:id/role', auth(['ADMIN']), userController.updateUserRole);
router.put('/:id', auth(['ADMIN']), userController.updateUser); // <-- add update user info
router.get('/:id', auth(['ADMIN']), userController.getUserById);
router.delete('/:id', auth(['ADMIN']), userController.deleteUser);
router.get('/', auth(['ADMIN']), userController.listUsers);

module.exports = router;