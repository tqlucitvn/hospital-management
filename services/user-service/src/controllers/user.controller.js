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
            select: { id: true, email: true, role: true, createdAt: true, updatedAt: true }
        });
        res.json(users);
    } catch (e) {
        res.status(500).json({ error: 'Could not list users' });
    }
};

exports.updateUserRole = async (req, res) => {
    try {
        const { id } = req.params;
        const { role } = req.body;
        if (!role) return res.status(400).json({ error: 'Missing role' });
        const updated = await prisma.user.update({
            where: { id },
            data: { role },
            select: { id: true, email: true, role: true, createdAt: true, updatedAt: true }
        });
        res.json(updated);
    } catch (e) {
        if (e.code === 'P2025') {
            return res.status(404).json({ error: 'User not found' });
        }
        res.status(500).json({ error: 'Could not update role' });
    }
};