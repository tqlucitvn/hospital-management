const { insertLog } = require('../lib/mongoLogs');

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
};