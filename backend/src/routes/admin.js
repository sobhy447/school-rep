const express = require('express');
const router = express.Router();
const { protect, authorize } = require('../middleware/auth');
const {
  getSettings,
  updateSettings,
  unlockGrades
} = require('../controllers/adminSettingsController');

router.get('/settings', protect, authorize('admin'), getSettings);
router.put('/settings', protect, authorize('admin'), updateSettings);
router.post('/unlock', protect, authorize('admin'), unlockGrades);

module.exports = router;
