const Notification = require('../models/Notification');
const asyncHandler = require('../utils/asyncHandler');
const ErrorResponse = require('../utils/ErrorResponse');

// @desc    Get user notifications
// @route   GET /api/notifications
// @access  Private
exports.getNotifications = asyncHandler(async (req, res, next) => {
  const notifications = await Notification.find({ recipient: req.user._id })
    .populate('sender', 'name')
    .sort({ createdAt: -1 })
    .limit(50);

  res.status(200).json({
    success: true,
    count: notifications.length,
    data: notifications
  });
});

// @desc    Mark notification as read
// @route   POST /api/notifications/:id/read
// @access  Private
exports.markAsRead = asyncHandler(async (req, res, next) => {
  const notification = await Notification.findById(req.params.id);

  if (!notification) {
    return next(new ErrorResponse('الإشعار غير موجود', 404));
  }

  if (notification.recipient.toString() !== req.user._id.toString()) {
    return next(new ErrorResponse('غير مصرح لك', 403));
  }

  notification.read = true;
  await notification.save();

  res.status(200).json({
    success: true,
    message: 'تم تعليم الإشعار كمقروء'
  });
});

// @desc    Mark all notifications as read
// @route   POST /api/notifications/read-all
// @access  Private
exports.markAllAsRead = asyncHandler(async (req, res, next) => {
  await Notification.updateMany(
    { recipient: req.user._id, read: false },
    { $set: { read: true } }
  );

  res.status(200).json({
    success: true,
    message: 'تم تعليم جميع الإشعارات كمقروءة'
  });
});

// @desc    Delete notification
// @route   DELETE /api/notifications/:id
// @access  Private
exports.deleteNotification = asyncHandler(async (req, res, next) => {
  const notification = await Notification.findById(req.params.id);

  if (!notification) {
    return next(new ErrorResponse('الإشعار غير موجود', 404));
  }

  if (notification.recipient.toString() !== req.user._id.toString()) {
    return next(new ErrorResponse('غير مصرح لك', 403));
  }

  await notification.deleteOne();

  res.status(200).json({
    success: true,
    message: 'تم حذف الإشعار'
  });
});

// @desc    Create notification (internal use)
// @access  Private
exports.createNotification = async (recipientId, senderId, type, title, message, data = {}) => {
  const notification = await Notification.create({
    recipient: recipientId,
    sender: senderId,
    type,
    title,
    message,
    data,
    read: false
  });

  return notification;
};
