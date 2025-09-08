const { insertLog } = require('../lib/mongoLogs');
const { sendMail } = require('../lib/email');

module.exports = async function handlePrescriptionEvent(payload, routingKey) {
    console.log(`[NOTIFY] ${routingKey} prescriptionId=${payload.id} status=${payload.status || ''} items=${payload.itemsCount || ''}`);
    insertLog('notification_events', {
        type: 'prescription',
        routingKey,
        prescriptionId: payload.id,
        status: payload.status,
        itemsCount: payload.itemsCount
    });
    if (process.env.ENABLE_EMAIL === 'true') {
        try {
            if (routingKey === 'prescription.created') {
                await sendMail(
                    'Prescription Created',
                    `<h3>New Prescription</h3>
                     <p>ID: ${payload.id}</p>
                     <p>Items: ${payload.itemsCount}</p>
                     <p>Status: ${payload.status}</p>`
                );
            } else if (routingKey.startsWith('prescription.status')) {
                await sendMail(
                    'Prescription Status Update',
                    `<h3>Prescription Update</h3>
                     <p>ID: ${payload.id}</p>
                     <p>Status: ${payload.status}</p>`
                );
            }
        } catch (e) {
            console.error('[notify][prescription][email] fail', e.message);
        }
    }
};