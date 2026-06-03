import React from 'react';
import { BookOpen, Users } from 'lucide-react';

const GRADE_CONFIG = {
  6: { 
    color: 'bg-yellow-500', 
    hover: 'hover:bg-yellow-600', 
    light: 'bg-yellow-50 border-yellow-200',
    text: 'text-yellow-700',
    ring: 'ring-yellow-300'
  },
  7: { 
    color: 'bg-green-500', 
    hover: 'hover:bg-green-600', 
    light: 'bg-green-50 border-green-200',
    text: 'text-green-700',
    ring: 'ring-green-300'
  },
  8: { 
    color: 'bg-red-500', 
    hover: 'hover:bg-red-600', 
    light: 'bg-red-50 border-red-200',
    text: 'text-red-700',
    ring: 'ring-red-300'
  },
  9: { 
    color: 'bg-blue-500', 
    hover: 'hover:bg-blue-600', 
    light: 'bg-blue-50 border-blue-200',
    text: 'text-blue-700',
    ring: 'ring-blue-300'
  }
};

const GRADE_NAMES = {
  6: 'السادس',
  7: 'السابع',
  8: 'الثامن',
  9: 'التاسع'
};

const ClassSelector = ({ classes, selectedClass, selectedSubject, onSelectClass, onSelectSubject }) => {
  // Group classes by grade level
  const groupedClasses = classes.reduce((acc, cls) => {
    const grade = cls.gradeLevel || 6;
    if (!acc[grade]) acc[grade] = [];
    acc[grade].push(cls);
    return acc;
  }, {});

  // Sort grades
  const sortedGrades = Object.keys(groupedClasses).sort((a, b) => parseInt(a) - parseInt(b));

  return (
    <div className="space-y-6">
      {sortedGrades.map(grade => {
        const config = GRADE_CONFIG[grade];
        const gradeClasses = groupedClasses[grade];

        return (
          <div key={grade} className="space-y-3">
            {/* Grade Header */}
            <div className={`flex items-center gap-2 px-3 py-2 rounded-lg ${config.light}`}>
              <div className={`w-3 h-3 rounded-full ${config.color}`}></div>
              <h3 className={`font-bold ${config.text}`}>
                المرحلة {GRADE_NAMES[grade]}
              </h3>
              <span className="text-xs text-gray-500 mr-auto">
                {gradeClasses.length} فصول
              </span>
            </div>

            {/* Classes Grid */}
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
              {gradeClasses.map(cls => {
                const isSelected = selectedClass === cls._id;
                const hasMultipleSubjects = cls.subjects && cls.subjects.length > 1;

                return (
                  <div key={cls._id} className="relative">
                    <button
                      onClick={() => onSelectClass(cls._id)}
                      className={`w-full p-4 rounded-xl border-2 transition-all duration-200 text-right
                        ${isSelected 
                          ? `${config.light} border-transparent ring-2 ${config.ring} shadow-md` 
                          : 'bg-white border-gray-200 hover:border-gray-300 hover:shadow-sm'
                        }
                      `}
                    >
                      <div className="flex items-start justify-between">
                        <div>
                          <div className={`font-bold text-lg ${isSelected ? config.text : 'text-gray-700'}`}>
                            {cls.name}
                          </div>
                          <div className="text-xs text-gray-500 mt-1 flex items-center gap-1">
                            <Users className="w-3 h-3" />
                            {cls.studentCount || 0} طالب
                          </div>
                        </div>
                        <div className={`w-10 h-10 rounded-lg ${config.color} flex items-center justify-center text-white`}>
                          <BookOpen className="w-5 h-5" />
                        </div>
                      </div>

                      {/* Subject indicator */}
                      {cls.subjectName && (
                        <div className="mt-2 text-xs text-gray-500 bg-gray-100 rounded px-2 py-1 inline-block">
                          {cls.subjectName}
                        </div>
                      )}
                    </button>

                    {/* Subject Selector (if multiple subjects) */}
                    {isSelected && hasMultipleSubjects && (
                      <div className="mt-2 p-2 bg-white rounded-lg border border-gray-200 shadow-sm">
                        <div className="text-xs text-gray-500 mb-2">اختر المادة:</div>
                        <div className="flex flex-wrap gap-2">
                          {cls.subjects.map(sub => (
                            <button
                              key={sub._id}
                              onClick={() => onSelectSubject(sub._id)}
                              className={`px-3 py-1.5 rounded-lg text-sm transition-all
                                ${selectedSubject === sub._id
                                  ? `${config.color} text-white shadow-sm`
                                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                }
                              `}
                            >
                              {sub.name}
                            </button>
                          ))}
                        </div>
                      </div>
                    )}
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
          <p>لا توجد فصول مخصصة لك</p>
        </div>
      )}
    </div>
  );
};

export default ClassSelector;
