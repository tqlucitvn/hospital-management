const { insertLog } = require('../lib/mongoLogs');
const { sendMail } = require('../lib/email');

module.exports = async function handleAppointmentEvent(payload, routingKey) {
    console.log(`[NOTIFY] ${routingKey}`, {
        id: payload.id,
        status: payload.status,
        startTime: payload.startTime,
        endTime: payload.endTime
    });
    insertLog('notification_events', {
        type: 'appointment',
        routingKey,
        appointmentId: payload.id,
        status: payload.status,
        startTime: payload.startTime,
        endTime: payload.endTime
    });
    if (process.env.ENABLE_EMAIL === 'true') {
        try {
            if (routingKey === 'appointment.created') {
                await sendMail(
                    'Appointment Created',
                    `<h3>New Appointment</h3>
                     <p>ID: ${payload.id}</p>
                     <p>Time: ${payload.startTime} - ${payload.endTime}</p>
                     <p>Status: ${payload.status}</p>`
                );
            } else if (routingKey.startsWith('appointment.status')) {
                await sendMail(
                    'Appointment Status Update',
                    `<h3>Appointment Update</h3>
                     <p>ID: ${payload.id}</p>
                     <p>New Status: ${payload.newStatus || payload.status}</p>`
                );
            }
        } catch (e) {
            console.error('[notify][appointment][email] fail', e.message);
        }
    }
};