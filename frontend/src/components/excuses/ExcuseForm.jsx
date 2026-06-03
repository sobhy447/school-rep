import React, { useState, useEffect } from 'react';
import { AlertCircle, CheckCircle, Save, X } from 'lucide-react';

const ExcuseForm = ({ 
  classes, 
  subjects, 
  students, 
  onLoadStudents, 
  onSubmit, 
  loading 
}) => {
  const [formData, setFormData] = useState({
    classId: '',
    studentId: '',
    subjectId: '',
    period: 'first',
    type: 'work',
    reason: ''
  });

  const [errors, setErrors] = useState({});
  const [showSuccess, setShowSuccess] = useState(false);

  // Load students when class changes
  useEffect(() => {
    if (formData.classId) {
      onLoadStudents(formData.classId);
    }
  }, [formData.classId, onLoadStudents]);

  const validate = () => {
    const newErrors = {};
    if (!formData.classId) newErrors.classId = 'الفصل مطلوب';
    if (!formData.studentId) newErrors.studentId = 'الطالب مطلوب';
    if (!formData.subjectId) newErrors.subjectId = 'المادة مطلوبة';
    if (!formData.reason.trim()) newErrors.reason = 'سبب العذر مطلوب';

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validate()) return;

    const success = await onSubmit(formData);
    if (success) {
      setShowSuccess(true);
      setFormData({
        classId: '',
        studentId: '',
        subjectId: '',
        period: 'first',
        type: 'work',
        reason: ''
      });
      setTimeout(() => setShowSuccess(false), 3000);
    }
  };

  const handleChange = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: null }));
    }
  };

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
      <h3 className="text-lg font-bold text-gray-800 mb-4">إضافة عذر جديد</h3>

      {showSuccess && (
        <div className="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg flex items-center gap-2 text-green-700">
          <CheckCircle className="w-5 h-5" />
          تم إضافة العذر بنجاح
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Class Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">الفصل</label>
            <select
              value={formData.classId}
              onChange={(e) => handleChange('classId', e.target.value)}
              className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500
                ${errors.classId ? 'border-red-300 bg-red-50' : 'border-gray-300'}
              `}
            >
              <option value="">اختر الفصل</option>
              {classes.map(cls => (
                <option key={cls._id} value={cls._id}>
                  {cls.name} (المرحلة {cls.gradeLevel})
                </option>
              ))}
            </select>
            {errors.classId && (
              <p className="text-sm text-red-600 mt-1">{errors.classId}</p>
            )}
          </div>

          {/* Student Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">الطالب</label>
            <select
              value={formData.studentId}
              onChange={(e) => handleChange('studentId', e.target.value)}
              disabled={!formData.classId || students.length === 0}
              className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500
                ${errors.studentId ? 'border-red-300 bg-red-50' : 'border-gray-300'}
                ${(!formData.classId || students.length === 0) ? 'bg-gray-100' : ''}
              `}
            >
              <option value="">اختر الطالب</option>
              {students.map(student => (
                <option key={student._id} value={student._id}>
                  {student.name} ({student.studentId})
                </option>
              ))}
            </select>
            {errors.studentId && (
              <p className="text-sm text-red-600 mt-1">{errors.studentId}</p>
            )}
          </div>

          {/* Subject Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">المادة</label>
            <select
              value={formData.subjectId}
              onChange={(e) => handleChange('subjectId', e.target.value)}
              className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500
                ${errors.subjectId ? 'border-red-300 bg-red-50' : 'border-gray-300'}
              `}
            >
              <option value="">اختر المادة</option>
              {subjects.map(subject => (
                <option key={subject._id} value={subject._id}>
                  {subject.name}
                </option>
              ))}
            </select>
            {errors.subjectId && (
              <p className="text-sm text-red-600 mt-1">{errors.subjectId}</p>
            )}
          </div>

          {/* Period */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">الفترة</label>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => handleChange('period', 'first')}
                className={`flex-1 py-2 px-3 rounded-lg border transition-all
                  ${formData.period === 'first'
                    ? 'bg-indigo-100 border-indigo-300 text-indigo-700 font-medium'
                    : 'border-gray-300 text-gray-600 hover:bg-gray-50'
                  }
                `}
              >
                الفترة الأولى
              </button>
              <button
                type="button"
                onClick={() => handleChange('period', 'second')}
                className={`flex-1 py-2 px-3 rounded-lg border transition-all
                  ${formData.period === 'second'
                    ? 'bg-indigo-100 border-indigo-300 text-indigo-700 font-medium'
                    : 'border-gray-300 text-gray-600 hover:bg-gray-50'
                  }
                `}
              >
                الفترة الثانية
              </button>
            </div>
          </div>

          {/* Type */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">النوع</label>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => handleChange('type', 'work')}
                className={`flex-1 py-2 px-3 rounded-lg border transition-all
                  ${formData.type === 'work'
                    ? 'bg-indigo-100 border-indigo-300 text-indigo-700 font-medium'
                    : 'border-gray-300 text-gray-600 hover:bg-gray-50'
                  }
                `}
              >
                الأعمال (40)
              </button>
              <button
                type="button"
                onClick={() => handleChange('type', 'exam')}
                className={`flex-1 py-2 px-3 rounded-lg border transition-all
                  ${formData.type === 'exam'
                    ? 'bg-indigo-100 border-indigo-300 text-indigo-700 font-medium'
                    : 'border-gray-300 text-gray-600 hover:bg-gray-50'
                  }
                `}
              >
                الاختبار (60)
              </button>
            </div>
          </div>

          {/* Reason */}
          <div className="md:col-span-2">
            <label className="block text-sm font-medium text-gray-700 mb-1">سبب العذر</label>
            <textarea
              value={formData.reason}
              onChange={(e) => handleChange('reason', e.target.value)}
              rows={3}
              className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500
                ${errors.reason ? 'border-red-300 bg-red-50' : 'border-gray-300'}
              `}
              placeholder="اكتب سبب العذر هنا..."
            />
            {errors.reason && (
              <p className="text-sm text-red-600 mt-1">{errors.reason}</p>
            )}
          </div>
        </div>

        {/* Submit */}
        <div className="flex justify-end">
          <button
            type="submit"
            disabled={loading}
            className="flex items-center gap-2 px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
          >
            {loading ? (
              <>
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                جاري الحفظ...
              </>
            ) : (
              <>
                <Save className="w-4 h-4" />
                حفظ العذر
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
};

export default ExcuseForm;
