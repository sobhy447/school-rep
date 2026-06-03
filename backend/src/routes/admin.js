const express = require('express');
const router = express.Router();
const { protect, authorize } = require('../middleware/auth');
const {
  getAdminSettings,
  updateAdminSettings,
  getAllApprovals,
  unlockApproval
} = require('../controllers/adminSettingsController');

// Settings routes
router.get('/settings', protect, authorize('admin'), getAdminSettings);
router.post('/settings', protect, authorize('admin'), updateAdminSettings);

// Approval routes
router.get('/approvals', protect, authorize('admin'), getAllApprovals);
router.post('/unlock', protect, authorize('admin'), unlockApproval);

module.exports = router;
