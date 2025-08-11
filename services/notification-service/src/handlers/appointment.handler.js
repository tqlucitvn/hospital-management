module.exports = async function handleAppointmentEvent(payload, routingKey) {
    // Có thể đọc correlationId nếu payload có
    console.log(`[NOTIFY] ${routingKey}`, {
        id: payload.id,
        status: payload.status,
        startTime: payload.startTime,
        endTime: payload.endTime
    });
    // TODO: gửi email / push / ghi audit
};