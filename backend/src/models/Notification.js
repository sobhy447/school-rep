const mongoose = require('mongoose');

const notificationSchema = new mongoose.Schema({
  recipient: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: [true, 'المستلم مطلوب']
  },
  sender: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: [true, 'المرسل مطلوب']
  },
  type: {
    type: String,
    enum: ['teacher_submitted', 'head_approved', 'head_edited', 'admin_unlocked'],
    required: [true, 'نوع الإشعار مطلوب']
  },
  title: {
    type: String,
    required: [true, 'عنوان الإشعار مطلوب']
  },
  message: {
    type: String,
    required: [true, 'محتوى الإشعار مطلوب']
  },
  data: {
    type: mongoose.Schema.Types.Mixed,
    default: {}
  },
  read: {
    type: Boolean,
    default: false
  },
  createdAt: {
    type: Date,
    default: Date.now
  }
});

// Indexes
notificationSchema.index({ recipient: 1, read: 1 });
notificationSchema.index({ recipient: 1, createdAt: -1 });

module.exports = mongoose.model('Notification', notificationSchema);
