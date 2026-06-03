import React from 'react';
import { Eye, CheckCircle, AlertCircle, User, BookOpen } from 'lucide-react';

const GRADE_CONFIG = {
  6: { color: 'bg-yellow-500', light: 'bg-yellow-50', text: 'text-yellow-700', border: 'border-yellow-200' },
  7: { color: 'bg-green-500', light: 'bg-green-50', text: 'text-green-700', border: 'border-green-200' },
  8: { color: 'bg-red-500', light: 'bg-red-50', text: 'text-red-700', border: 'border-red-200' },
  9: { color: 'bg-blue-500', light: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-200' }
};

const GRADE_NAMES = { 6: 'السادس', 7: 'السابع', 8: 'الثامن', 9: 'التاسع' };

const HeadClassView = ({ classes, onReview, onEnterGrades, period }) => {
  // Group by grade level
  const grouped = classes.reduce((acc, cls) => {
    const grade = cls.gradeLevel || 6;
    if (!acc[grade]) acc[grade] = [];
    acc[grade].push(cls);
    return acc;
  }, {});

  const sortedGrades = Object.keys(grouped).sort((a, b) => parseInt(a) - parseInt(b));

  return (
    <div className="space-y-6">
      {sortedGrades.map(grade => {
        const config = GRADE_CONFIG[grade];
        const gradeClasses = grouped[grade];

        return (
          <div key={grade} className="space-y-3">
            {/* Grade Header */}
            <div className={`flex items-center gap-2 px-4 py-3 rounded-xl ${config.light} ${config.border} border`}>
              <div className={`w-4 h-4 rounded-full ${config.color}`}></div>
              <h3 className={`font-bold text-lg ${config.text}`}>
                المرحلة {GRADE_NAMES[grade]}
              </h3>
              <span className="text-sm text-gray-500 mr-auto">
                {gradeClasses.length} فصول
              </span>
            </div>

            {/* Classes Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {gradeClasses.map(cls => {
                const workApproved = cls.workApproved;
                const examApproved = cls.examApproved;
                const isOwnClass = cls.isHeadClass; // Class taught by head himself

                return (
                  <div 
                    key={cls._id} 
                    className={`bg-white rounded-xl border-2 p-4 transition-all hover:shadow-md
                      ${isOwnClass ? 'border-blue-300 ring-2 ring-blue-100' : 'border-gray-200'}
                    `}
                  >
                    {/* Class Header */}
                    <div className="flex items-start justify-between mb-3">
                      <div>
                        <div className="font-bold text-gray-800 text-lg">{cls.name}</div>
                        <div className="flex items-center gap-2 mt-1 text-sm text-gray-600">
                          <User className="w-4 h-4" />
                          <span>{cls.teacherName}</span>
                          {isOwnClass && (
                            <span className="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">
                              فصلي
                            </span>
                          )}
                        </div>
                      </div>
                      <div className={`w-10 h-10 rounded-lg ${config.color} flex items-center justify-center text-white`}>
                        <BookOpen className="w-5 h-5" />
                      </div>
                    </div>

                    {/* Subject */}
                    <div className="text-sm text-gray-500 mb-3 bg-gray-50 rounded-lg px-3 py-2">
                      <BookOpen className="w-4 h-4 inline ml-1" />
                      {cls.subjectName}
                    </div>

                    {/* Approval Status */}
                    <div className="space-y-2 mb-4">
                      {/* Work Status */}
                      <div className={`flex items-center justify-between px-3 py-2 rounded-lg border
                        ${workApproved 
                          ? 'bg-green-50 border-green-200' 
                          : 'bg-yellow-50 border-yellow-200'
                        }
                      `}>
                        <div className="flex items-center gap-2">
                          {workApproved 
                            ? <CheckCircle className="w-4 h-4 text-green-600" />
                            : <AlertCircle className="w-4 h-4 text-yellow-600" />
                          }
                          <span className={`text-sm font-medium ${workApproved ? 'text-green-700' : 'text-yellow-700'}`}>
                            الأعمال
                          </span>
                        </div>
                        <span className={`text-xs px-2 py-1 rounded-full font-medium
                          ${workApproved ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}
                        `}>
                          {workApproved ? 'معتمد' : 'قيد الانتظار'}
                        </span>
                      </div>

                      {/* Exam Status */}
                      <div className={`flex items-center justify-between px-3 py-2 rounded-lg border
                        ${examApproved 
                          ? 'bg-green-50 border-green-200' 
                          : 'bg-yellow-50 border-yellow-200'
                        }
                      `}>
                        <div className="flex items-center gap-2">
                          {examApproved 
                            ? <CheckCircle className="w-4 h-4 text-green-600" />
                            : <AlertCircle className="w-4 h-4 text-yellow-600" />
                          }
                          <span className={`text-sm font-medium ${examApproved ? 'text-green-700' : 'text-yellow-700'}`}>
                            الاختبار
                          </span>
                        </div>
                        <span className={`text-xs px-2 py-1 rounded-full font-medium
                          ${examApproved ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}
                        `}>
                          {examApproved ? 'معتمد' : 'قيد الانتظار'}
                        </span>
                      </div>
                    </div>

                    {/* Actions */}
                    <div className="flex gap-2">
                      {/* Preview/Review Button */}
                      <button
                        onClick={() => onReview(cls._id, cls.teacherId, cls.subjectId)}
                        className="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium"
                      >
                        <Eye className="w-4 h-4" />
                        عاين الدرجات
                      </button>

                      {/* Enter Grades (only for head's own classes) */}
                      {isOwnClass && (
                        <button
                          onClick={() => onEnterGrades(cls._id, cls.subjectId)}
                          className="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
                        >
                          <BookOpen className="w-4 h-4" />
                          رصد درجاتي
                        </button>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        );
      })}

      {classes.length === 0 && (
        <div className="text-center py-12 text-gray-500">
          <BookOpen className="w-12 h-12 mx-auto mb-3 text-gray-300" />
          <p>لا توجد فصول مخصصة لقسمك</p>
        </div>
      )}
    </div>
  );
};

export default HeadClassView;
