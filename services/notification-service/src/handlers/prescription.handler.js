const { insertLog } = require('../lib/mongoLogs');

module.exports = async function handlePrescriptionEvent(payload, routingKey) {
    console.log(`[NOTIFY] ${routingKey} prescriptionId=${payload.id} status=${payload.status || ''} items=${payload.itemsCount || ''}`);
    insertLog('notification_events', {
        type: 'prescription',
        routingKey,
        prescriptionId: payload.id,
        status: payload.status,
        itemsCount: payload.itemsCount
    });
};