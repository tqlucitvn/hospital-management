module.exports.createAppointment = (req, res, next) => {
    const { patientId, doctorId, startTime, endTime } = req.body;
    if (!patientId || !doctorId || !startTime || !endTime) {
        return res.status(400).json({ error: 'patientId, doctorId, startTime, endTime required' });
    }
    next();
};

module.exports.updateStatus = (req, res, next) => {
    const { status } = req.body;
    const allowed = ['SCHEDULED', 'CONFIRMED', 'COMPLETED', 'CANCELED'];
    if (!status || !allowed.includes(status)) {
        return res.status(400).json({ error: 'status required or invalid' });
    }
    next();
};