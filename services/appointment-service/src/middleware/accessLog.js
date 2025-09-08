const { insertLog } = require('../lib/mongoLogs');

module.exports = (req, res, next) => {
    const start = process.hrtime.bigint();
    res.on('finish', () => {
        const ms = Number(process.hrtime.bigint() - start) / 1e6;
        const line = `[http] ${req.method} ${req.originalUrl} ${res.statusCode} ${ms.toFixed(1)}ms rid=${req.requestId} cid=${req.correlationId}`;
        console.log(line);
        insertLog('access_logs', {
            method: req.method,
            path: req.originalUrl,
            status: res.statusCode,
            ms: Number(ms.toFixed(1)),
            rid: req.requestId,
            cid: req.correlationId,
            ua: req.headers['user-agent'] || ''
        });
    });
    next();
};