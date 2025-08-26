const { PrismaClient } = require('@prisma/client');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');

const prisma = new PrismaClient();
const JWT_SECRET = process.env.JWT_SECRET;

exports.register = async (req, res) => {
    try {
        const { email, password, role } = req.body;
        if (!email || !password || !role) {
            return res.status(400).json({ error: 'Email, password, and role are required.' });
        }
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email))
            return res.status(400).json({ error: 'Invalid email format' });
        if (password.length < 6)
            return res.status(400).json({ error: 'Password too short' });
        const hashedPassword = await bcrypt.hash(password, 10);
        const newUser = await prisma.user.create({
            data: { email, password: hashedPassword, role },
        });
        const { password: _, ...userWithoutPassword } = newUser;
        res.status(201).json(userWithoutPassword);
    } catch (error) {
        if (error.code === 'P2002') {
            return res.status(409).json({ error: 'Email already exists.' });
        }
        res.status(500).json({ error: 'Could not register user.', details: error.message });
    }
};

exports.login = async (req, res) => {
    try {
        const { email, password } = req.body;
        const user = await prisma.user.findUnique({ where: { email } });
        if (!user) {
            return res.status(401).json({ error: 'Invalid credentials.' });
        }
        const isPasswordValid = await bcrypt.compare(password, user.password);
        if (!isPasswordValid) {
            return res.status(401).json({ error: 'Invalid credentials.' });
        }
        const token = jwt.sign({ id: user.id, role: user.role }, JWT_SECRET, { expiresIn: '8h' });
        res.status(200).json({ token });
    } catch (error) {
        res.status(500).json({ error: 'Could not log in.', details: error.message });
    }
};


exports.listUsers = async (_req, res) => {
    try {
        const users = await prisma.user.findMany({
            orderBy: { createdAt: 'desc' },
            select: {
                id: true,
                email: true,
                role: true,
                fullName: true,
                phoneNumber: true,
                address: true,
                createdAt: true,
                updatedAt: true
            }
        });
        res.json(users);
    } catch (e) {
        res.status(500).json({ error: 'Could not list users' });
    }
};

exports.getUserById = async (req, res) => {
    try {
        const { id } = req.params;
        const user = await prisma.user.findUnique({
            where: { id },
            select: {
                id: true,
                email: true,
                role: true,
                fullName: true,
                phoneNumber: true,
                address: true,
                createdAt: true,
                updatedAt: true
            }
        });

        if (!user) {
            return res.status(404).json({ error: 'User not found' });
        }

        res.json(user);
    } catch (e) {
        res.status(500).json({ error: 'Could not get user details' });
    }
};

exports.updateUserRole = async (req, res) => {
    try {
        const { id } = req.params;
        const { role } = req.body;

        console.log(`Update role request - ID: ${id}, Role: "${role}", Type: ${typeof role}`);
        console.log('Full request body:', req.body);

        if (!role) {
            console.log('Missing role in request');
            return res.status(400).json({ error: 'Missing role' });
        }

        // Validate role enum
        const validRoles = ['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST'];
        if (!validRoles.includes(role)) {
            console.log(`Invalid role: "${role}". Valid roles:`, validRoles);
            return res.status(400).json({ error: 'Invalid role. Must be one of: ' + validRoles.join(', ') });
        }

        const updated = await prisma.user.update({
            where: { id },
            data: { role },
            select: { id: true, email: true, role: true, createdAt: true, updatedAt: true }
        });

        console.log('Role updated successfully:', updated);
        res.json(updated);
    } catch (e) {
        console.log('Update role error:', e.message);
        console.log('Error details:', e);
        if (e.code === 'P2025') {
            return res.status(404).json({ error: 'User not found' });
        }
        res.status(500).json({ error: 'Could not update role' });
    }
};

exports.updateUser = async (req, res) => {
    try {
        const { id } = req.params;
        const { email, role, fullName, phoneNumber, address, password } = req.body;
        const data = {};
        if (email) data.email = email;
        if (role) data.role = role;
        if (fullName !== undefined) data.fullName = fullName;
        if (phoneNumber !== undefined) data.phoneNumber = phoneNumber;
        if (address !== undefined) data.address = address;
        if (password) {
            data.password = await bcrypt.hash(password, 10);
        }
        const updated = await prisma.user.update({
            where: { id },
            data,
            select: { id: true, email: true, role: true, fullName: true, phoneNumber: true, address: true, createdAt: true, updatedAt: true }
        });
        res.json(updated);
    } catch (e) {
        if (e.code === 'P2025') {
            return res.status(404).json({ error: 'User not found' });
        }
        res.status(500).json({ error: 'Could not update user', details: e.message });
    }
};

exports.deleteUser = async (req, res) => {
    try {
        const { id } = req.params;

        // Check if user exists
        const user = await prisma.user.findUnique({
            where: { id },
            select: { id: true, email: true }
        });

        if (!user) {
            return res.status(404).json({ error: 'User not found' });
        }

        // Delete user
        await prisma.user.delete({
            where: { id }
        });

        res.status(204).send();
    } catch (e) {
        if (e.code === 'P2025') {
            return res.status(404).json({ error: 'User not found' });
        }
        res.status(500).json({ error: 'Could not delete user', details: e.message });
    }
};