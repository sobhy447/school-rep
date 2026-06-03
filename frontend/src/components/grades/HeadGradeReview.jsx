import React, { useState } from 'react';
import { ArrowRight, Save, CheckCircle, AlertCircle, Edit3, Lock, X } from 'lucide-react';

const HeadGradeReview = ({ 
  reviewData, 
  onEditGrade, 
  onSave, 
  onBack,
  loading 
}) => {
  const [editMode, setEditMode] = useState(false);
  const [localEdits, setLocalEdits] = useState({});
  const [showConfirm, setShowConfirm] = useState(false);

  if (!reviewData) return null;

  const { classInfo, teacherInfo, subjectInfo, period, grades, students, excuses, approvalStatus } = reviewData;

  const handleEdit = (studentId, type, value) => {
    const max = type === 'work' ? 40 : 60;
    let numValue = parseFloat(value);

    if (isNaN(numValue) || numValue < 0) numValue = '';
    else if (numValue > max) numValue = max;
    else {
      const decimal = numValue % 1;
      if (decimal !== 0 && decimal !== 0.5) {
        numValue = Math.floor(numValue);
      }
    }

    setLocalEdits(prev => ({
      ...prev,
      [studentId]: {
        ...prev[studentId],
        [type]: numValue
      }
    }));
  };

  const handleSaveEdits = async () => {
    // Apply all edits
    for (const [studentId, types] of Object.entries(localEdits)) {
      for (const [type, value] of Object.entries(types)) {
        if (value !== undefined && value !== '') {
          await onEditGrade(studentId, type, value);
        }
      }
    }
    setEditMode(false);
    setLocalEdits({});
  };

  const getGradeColor = (grade, max) => {
    if (!grade && grade !== 0) return 'text-gray-400';
    const pct = (grade / max) * 100;
    if (pct >= 90) return 'text-green-600 font-bold';
    if (pct >= 75) return 'text-blue-600 font-semibold';
    if (pct >= 60) return 'text-yellow-600';
    return 'text-red-600 font-semibold';
  };

  const isWorkApproved = approvalStatus?.work === 'submitted' || approvalStatus?.work === 'approved';
  const isExamApproved = approvalStatus?.exam === 'submitted' || approvalStatus?.exam === 'approved';

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm">
      {/* Header */}
      <div className="p-4 border-b border-gray-200">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <button
              onClick={onBack}
              className="flex items-center gap-2 text-gray-600 hover:text-gray-800 transition-colors"
            >
              <ArrowRight className="w-5 h-5" />
              رجوع
            </button>
            <div className="h-6 w-px bg-gray-300"></div>
            <div>
              <h2 className="font-bold text-gray-800">مراجعة الدرجات</h2>
              <p className="text-sm text-gray-500">
                {classInfo?.name} | {teacherInfo?.name} | {subjectInfo?.name} | 
                الفترة {period === 'first' ? 'الأولى' : 'الثانية'}
              </p>
            </div>
          </div>

          {/* Edit Toggle */}
          {!isWorkApproved && !isExamApproved && (
            <button
              onClick={() => {
                if (editMode) {
                  setEditMode(false);
                  setLocalEdits({});
                } else {
                  setEditMode(true);
                }
              }}
              className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-colors
                ${editMode 
                  ? 'bg-red-100 text-red-700 hover:bg-red-200' 
                  : 'bg-blue-100 text-blue-700 hover:bg-blue-200'
                }
              `}
            >
              {editMode ? <X className="w-4 h-4" /> : <Edit3 className="w-4 h-4" />}
              {editMode ? 'إلغاء التعديل' : 'تعديل الدرجات'}
            </button>
          )}
        </div>
      </div>

      {/* Approval Status Banner */}
      <div className="px-4 py-3 bg-gray-50 border-b border-gray-200">
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-600">حالة الاعتماد:</span>
          <span className={`flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium
            ${isWorkApproved ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}
          `}>
            {isWorkApproved ? <CheckCircle className="w-3 h-3" /> : <AlertCircle className="w-3 h-3" />}
            الأعمال: {isWorkApproved ? 'معتمد' : 'قيد الانتظار'}
          </span>
          <span className={`flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium
            ${isExamApproved ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}
          `}>
            {isExamApproved ? <CheckCircle className="w-3 h-3" /> : <AlertCircle className="w-3 h-3" />}
            الاختبار: {isExamApproved ? 'معتمد' : 'قيد الانتظار'}
          </span>
        </div>
      </div>

      {/* Grades Table */}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-gray-50 text-gray-700">
              <th className="px-3 py-3 text-right font-semibold border-b w-12">#</th>
              <th className="px-3 py-3 text-right font-semibold border-b">اسم الطالب</th>
              <th className="px-3 py-3 text-center font-semibold border-b w-32">الأعمال (40)</th>
              <th className="px-3 py-3 text-center font-semibold border-b w-32">الاختبار (60)</th>
              <th className="px-3 py-3 text-center font-semibold border-b w-24">الإجمالي</th>
              <th className="px-3 py-3 text-center font-semibold border-b w-20">التعديل</th>
            </tr>
          </thead>
          <tbody>
            {students.map((student, index) => {
              const studentGrades = grades[student._id] || {};
              const workGrade = localEdits[student._id]?.work !== undefined 
                ? localEdits[student._id].work 
                : studentGrades.work;
              const examGrade = localEdits[student._id]?.exam !== undefined 
                ? localEdits[student._id].exam 
                : studentGrades.exam;
              const total = (workGrade || 0) + (examGrade || 0);
              const workExcuse = excuses[student._id]?.work;
              const examExcuse = excuses[student._id]?.exam;
              const wasEdited = studentGrades.workEditedByHead || studentGrades.examEditedByHead;

              return (
                <tr key={student._id} className={`border-b ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'}`}>
                  <td className="px-3 py-2 text-center text-gray-500">{index + 1}</td>
                  <td className="px-3 py-2">
                    <div className="font-medium text-gray-800">{student.name}</div>
                    <div className="text-xs text-gray-500">{student.studentId || student._id.slice(-4)}</div>
                  </td>

                  {/* Work */}
                  <td className="px-3 py-2">
                    <div className="flex items-center justify-center">
                      {workExcuse ? (
                        <div className="flex items-center text-red-500 text-xs">
                          <Lock className="w-3 h-3 ml-1" />
                          <span>معذور</span>
                        </div>
                      ) : editMode && !isWorkApproved ? (
                        <input
                          type="number"
                          step={0.5}
                          min={0}
                          max={40}
                          value={workGrade !== undefined && workGrade !== '' ? workGrade : ''}
                          onChange={(e) => handleEdit(student._id, 'work', e.target.value)}
                          className="w-20 text-center py-1.5 px-2 rounded border-2 border-blue-300 bg-blue-50 focus:border-blue-500 focus:outline-none text-lg font-medium"
                        />
                      ) : (
                        <span className={`text-lg ${getGradeColor(workGrade, 40)}`}>
                          {workGrade !== undefined && workGrade !== '' ? workGrade : '-'}
                        </span>
                      )}
                    </div>
                  </td>

                  {/* Exam */}
                  <td className="px-3 py-2">
                    <div className="flex items-center justify-center">
                      {examExcuse ? (
                        <div className="flex items-center text-red-500 text-xs">
                          <Lock className="w-3 h-3 ml-1" />
                          <span>معذور</span>
                        </div>
                      ) : editMode && !isExamApproved ? (
                        <input
                          type="number"
                          step={0.5}
                          min={0}
                          max={60}
                          value={examGrade !== undefined && examGrade !== '' ? examGrade : ''}
                          onChange={(e) => handleEdit(student._id, 'exam', e.target.value)}
                          className="w-20 text-center py-1.5 px-2 rounded border-2 border-blue-300 bg-blue-50 focus:border-blue-500 focus:outline-none text-lg font-medium"
                        />
                      ) : (
                        <span className={`text-lg ${getGradeColor(examGrade, 60)}`}>
                          {examGrade !== undefined && examGrade !== '' ? examGrade : '-'}
                        </span>
                      )}
                    </div>
                  </td>

                  {/* Total */}
                  <td className="px-3 py-2 text-center">
                    <span className={`text-lg font-bold ${
                      total >= 90 ? 'text-green-600' : 
                      total >= 75 ? 'text-blue-600' : 
                      total >= 60 ? 'text-yellow-600' : 'text-red-600'
                    }`}>
                      {(workGrade !== undefined && workGrade !== '' && examGrade !== undefined && examGrade !== '') 
                        ? total : '-'}
                    </span>
                  </td>

                  {/* Edited Indicator */}
                  <td className="px-3 py-2 text-center">
                    {wasEdited && (
                      <span className="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs">
                        <Edit3 className="w-3 h-3" />
                        تم التعديل
                      </span>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Save Button (when editing) */}
      {editMode && (
        <div className="p-4 border-t border-gray-200 bg-gray-50">
          <div className="flex items-center justify-between">
            <p className="text-sm text-gray-600">
              <AlertCircle className="w-4 h-4 inline ml-1" />
              التعديلات تظهر للمعلم بعد الحفظ
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => {
                  setEditMode(false);
                  setLocalEdits({});
                }}
                className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors"
              >
                إلغاء
              </button>
              <button
                onClick={() => setShowConfirm(true)}
                className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                <Save className="w-4 h-4" />
                حفظ التعديلات
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Confirm Modal */}
      {showConfirm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
            <div className="flex items-center gap-3 mb-4 text-amber-600">
              <AlertCircle className="w-8 h-8" />
              <h3 className="text-lg font-bold">تأكيد الحفظ</h3>
            </div>
            <p className="text-gray-600 mb-6 leading-relaxed">
              هل أنت متأكد من حفظ التعديلات؟
              <br />
              <span className="text-red-500 text-sm">
                التعديلات ستظهر للمعلم ويتم تسجيلها باسمك كرئيس قسم.
              </span>
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowConfirm(false)}
                className="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
              >
                إلغاء
              </button>
              <button
                onClick={() => {
                  handleSaveEdits();
                  setShowConfirm(false);
                }}
                className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                تأكيد الحفظ
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default HeadGradeReview;
