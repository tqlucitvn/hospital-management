const { randomUUID } = require('crypto');
module.exports = (req, _res, next) => {
    const rid = req.headers['x-request-id'];
    const cid = req.headers['x-correlation-id'];
    req.requestId = rid ? String(rid) : randomUUID();
    req.correlationId = cid ? String(cid) : req.requestId;
    next();
};