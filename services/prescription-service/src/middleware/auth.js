const jwt = require('jsonwebtoken');
module.exports = (roles = []) => {
    if (!Array.isArray(roles)) roles = [roles];
    return (req, res, next) => {
        const h = req.headers.authorization;
        if (!h || !h.startsWith('Bearer ')) return res.status(401).json({ error: 'Missing token' });
        try {
            const decoded = jwt.verify(h.split(' ')[1], process.env.JWT_SECRET || 'your-super-secret-key-for-jwt');
            if (roles.length && !roles.includes(decoded.role)) return res.status(403).json({ error: 'Forbidden' });
            req.user = decoded;
            next();
        } catch {
            return res.status(401).json({ error: 'Invalid token' });
        }
    };
};