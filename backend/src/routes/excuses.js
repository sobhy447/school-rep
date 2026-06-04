const express = require('express');
const router = express.Router();
const { protect, authorize } = require('../middleware/auth');
const {
  getExcuses,
  createExcuse,
  deleteExcuse
} = require('../controllers/excuseController');

router.get('/', protect, getExcuses);
router.post('/', protect, authorize('teacher', 'head', 'admin'), createExcuse);
router.delete('/:id', protect, authorize('head', 'admin'), deleteExcuse);

module.exports = router;
