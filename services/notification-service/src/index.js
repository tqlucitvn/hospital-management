require('dotenv').config();
const { subscribe, close } = require('./broker');
const handleAppointmentEvent = require('./handlers/appointment.handler');
const handlePrescriptionEvent = require('./handlers/prescription.handler');

(async () => {
    console.log('[notification] starting...');
    await subscribe('appointment.events', 'appointment.*', handleAppointmentEvent, 'notification_appointment_q');
    await subscribe('prescription.events', 'prescription.*', handlePrescriptionEvent, 'notification_prescription_q');
    console.log('[notification] ready, waiting for events');
})().catch(e => {
    console.error('[notification] fatal start error:', e);
    process.exit(1);
});

async function shutdown() {
    console.log('[notification] shutting down...');
    await close();
    process.exit(0);
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);