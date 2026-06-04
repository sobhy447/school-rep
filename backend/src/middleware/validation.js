const { body, validationResult } = require('express-validator');

exports.validate = (validations) => {
  return async (req, res, next) => {
    await Promise.all(validations.map(validation => validation.run(req)));
    const errors = validationResult(req);
    if (errors.isEmpty()) {
      return next();
    }
    res.status(400).json({ errors: errors.array() });
  };
};

exports.gradeValidation = [
  body('studentId').notEmpty().withMessage('Student ID is required'),
  body('subjectId').notEmpty().withMessage('Subject ID is required'),
  body('classwork').optional().isFloat({ min: 0, max: 40 }).withMessage('Classwork must be between 0 and 40'),
  body('exam').optional().isFloat({ min: 0, max: 60 }).withMessage('Exam must be between 0 and 60'),
];
