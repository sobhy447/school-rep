import React, { useState, useEffect } from 'react';
import { Save, Send, AlertCircle, CheckCircle, Lock } from 'lucide-react';

const GRADE_COLORS = {
  6: 'bg-yellow-500 hover:bg-yellow-600',
  7: 'bg-green-500 hover:bg-green-600',
  8: 'bg-red-500 hover:bg-red-600',
  9: 'bg-blue-500 hover:bg-blue-600'
};

const GRADE_BG_COLORS = {
  6: 'bg-yellow-50 border-yellow-200',
  7: 'bg-green-50 border-green-200',
  8: 'bg-red-50 border-red-200',
  9: 'bg-blue-50 border-blue-200'
};

const GradeEntryTable = ({ 
  students, 
  grades, 
  excuses, 
  approvalStatus,
  settings,
  onUpdateGrade, 
  onSave, 
  onSubmit,
  canEdit,
  hasExcuse,
  loading 
}) => {
  const [localGrades, setLocalGrades] = useState({});
  const [showConfirm, setShowConfirm] = useState(null);

  useEffect(() => {
    setLocalGrades(grades);
  }, [grades]);

  const handleGradeChange = (studentId, type, value) => {
    const max = type === 'work' ? settings.workMax : settings.examMax;
    let numValue = parseFloat(value);

    if (isNaN(numValue) || numValue < 0) numValue = '';
    else if (numValue > max) numValue = max;
    else {
      // Handle half points
      if (settings.allowHalf) {
        const decimal = numValue % 1;
        if (decimal !== 0 && decimal !== 0.5) {
          numValue = Math.floor(numValue);
        }
      } else {
        numValue = Math.floor(numValue);
      }
    }

    setLocalGrades(prev => ({
      ...prev,
      [studentId]: {
        ...prev[studentId],
        [type]: numValue
      }
    }));

    onUpdateGrade(studentId, type, numValue);
  };

  const getGradeColor = (grade, max) => {
    if (!grade && grade !== 0) return 'text-gray-400';
    const percentage = (grade / max) * 100;
    if (percentage >= 90) return 'text-green-600 font-bold';
    if (percentage >= 75) return 'text-blue-600 font-semibold';
    if (percentage >= 60) return 'text-yellow-600';
    return 'text-red-600 font-semibold';
  };

  const getGradeLevel = (gradeId) => {
    const grade = students.find(s => s._id === gradeId)?.gradeLevel;
    return grade || 6;
  };

  const isWorkApproved = approvalStatus?.work === 'submitted' || approvalStatus?.work === 'approved' || approvalStatus?.work === 'head_approved';
  const isExamApproved = approvalStatus?.exam === 'submitted' || approvalStatus?.exam === 'approved' || approvalStatus?.exam === 'head_approved';

  const canSubmitWork = !isWorkApproved && students.every(s => 
    localGrades[s._id]?.work !== undefined && localGrades[s._id]?.work !== '' || hasExcuse(s._id, 'work')
  );

  const canSubmitExam = !isExamApproved && students.every(s => 
    localGrades[s._id]?.exam !== undefined && localGrades[s._id]?.exam !== '' || hasExcuse(s._id, 'exam')
  );

  return (
    <div className="w-full">
      {/* Header Info */}
      <div className="mb-4 flex items-center justify-between">
        <div className="text-sm text-gray-600">
          <span className="inline-flex items-center mr-4">
            <span className="w-3 h-3 rounded-full bg-green-500 mr-1"></span>
            محفوظ
          </span>
          <span className="inline-flex items-center mr-4">
            <span className="w-3 h-3 rounded-full bg-yellow-500 mr-1"></span>
            غير محفوظ
          </span>
          <span className="inline-flex items-center">
            <Lock className="w-3 h-3 mr-1 text-red-500" />
            معتمد
          </span>
        </div>
        <div className="text-sm text-gray-500">
          الأعمال: <span className="font-bold text-gray-800">{settings.workMax}</span> | 
          الاختبار: <span className="font-bold text-gray-800">{settings.examMax}</span>
        </div>
      </div>

      {/* Table */}
      <div className="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-gray-50 text-gray-700">
              <th className="px-3 py-3 text-right font-semibold border-b w-12">#</th>
              <th className="px-3 py-3 text-right font-semibold border-b">اسم الطالب</th>
              <th className="px-3 py-3 text-center font-semibold border-b w-32">
                <div className="flex flex-col items-center">
                  <span>الأعمال</span>
                  <span className="text-xs text-gray-500">({settings.workMax})</span>
                </div>
              </th>
              <th className="px-3 py-3 text-center font-semibold border-b w-32">
                <div className="flex flex-col items-center">
                  <span>الاختبار</span>
                  <span className="text-xs text-gray-500">({settings.examMax})</span>
                </div>
              </th>
              <th className="px-3 py-3 text-center font-semibold border-b w-24">الإجمالي</th>
            </tr>
          </thead>
          <tbody>
            {students.map((student, index) => {
              const gradeLevel = student.gradeLevel || 6;
              const studentGrades = localGrades[student._id] || {};
              const workGrade = studentGrades.work;
              const examGrade = studentGrades.exam;
              const total = (workGrade || 0) + (examGrade || 0);
              const workExcuse = hasExcuse(student._id, 'work');
              const examExcuse = hasExcuse(student._id, 'exam');
              const workSaved = studentGrades.workSaved;
              const examSaved = studentGrades.examSaved;

              return (
                <tr 
                  key={student._id} 
                  className={`border-b transition-colors hover:bg-gray-50 ${
                    index % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'
                  }`}
                >
                  <td className="px-3 py-2 text-center text-gray-500">{index + 1}</td>
                  <td className="px-3 py-2">
                    <div className="flex items-center">
                      <span className={`w-2 h-8 rounded-full ml-2 ${GRADE_COLORS[gradeLevel].split(' ')[0]}`}></span>
                      <div>
                        <div className="font-medium text-gray-800">{student.name}</div>
                        <div className="text-xs text-gray-500">{student.studentId || student._id.slice(-4)}</div>
                      </div>
                    </div>
                  </td>

                  {/* Work Grade */}
                  <td className="px-3 py-2">
                    <div className="flex items-center justify-center">
                      {workExcuse ? (
                        <div className="flex items-center text-red-500 text-xs">
                          <Lock className="w-3 h-3 ml-1" />
                          <span>معذور</span>
                        </div>
                      ) : isWorkApproved ? (
                        <span className={`text-lg ${getGradeColor(workGrade, settings.workMax)}`}>
                          {workGrade !== undefined && workGrade !== '' ? workGrade : '-'}
                        </span>
                      ) : (
                        <div className="relative">
                          <input
                            type="number"
                            step={settings.allowHalf ? 0.5 : 1}
                            min={0}
                            max={settings.workMax}
                            value={workGrade !== undefined && workGrade !== '' ? workGrade : ''}
                            onChange={(e) => handleGradeChange(student._id, 'work', e.target.value)}
                            className={`w-20 text-center py-1.5 px-2 rounded border-2 transition-all text-lg font-medium
                              ${workSaved ? 'border-green-300 bg-green-50' : 'border-yellow-300 bg-yellow-50'}
                              focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100
                              ${getGradeColor(workGrade, settings.workMax)}
                            `}
                            placeholder="-"
                            disabled={loading}
                          />
                          {workSaved && (
                            <CheckCircle className="absolute -top-1 -right-1 w-4 h-4 text-green-500" />
                          )}
                        </div>
                      )}
                    </div>
                  </td>

                  {/* Exam Grade */}
                  <td className="px-3 py-2">
                    <div className="flex items-center justify-center">
                      {examExcuse ? (
                        <div className="flex items-center text-red-500 text-xs">
                          <Lock className="w-3 h-3 ml-1" />
                          <span>معذور</span>
                        </div>
                      ) : isExamApproved ? (
                        <span className={`text-lg ${getGradeColor(examGrade, settings.examMax)}`}>
                          {examGrade !== undefined && examGrade !== '' ? examGrade : '-'}
                        </span>
                      ) : (
                        <div className="relative">
                          <input
                            type="number"
                            step={settings.allowHalf ? 0.5 : 1}
                            min={0}
                            max={settings.examMax}
                            value={examGrade !== undefined && examGrade !== '' ? examGrade : ''}
                            onChange={(e) => handleGradeChange(student._id, 'exam', e.target.value)}
                            className={`w-20 text-center py-1.5 px-2 rounded border-2 transition-all text-lg font-medium
                              ${examSaved ? 'border-green-300 bg-green-50' : 'border-yellow-300 bg-yellow-50'}
                              focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100
                              ${getGradeColor(examGrade, settings.examMax)}
                            `}
                            placeholder="-"
                            disabled={loading}
                          />
                          {examSaved && (
                            <CheckCircle className="absolute -top-1 -right-1 w-4 h-4 text-green-500" />
                          )}
                        </div>
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
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Submit Buttons */}
      <div className="mt-6 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <button
            onClick={onSave}
            disabled={loading || (isWorkApproved && isExamApproved)}
            className="flex items-center gap-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 
              disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <Save className="w-4 h-4" />
            حفظ المسودة
          </button>
        </div>

        <div className="flex items-center gap-3">
          {/* Work Submit */}
          {settings.workEnabled && (
            <button
              onClick={() => setShowConfirm('work')}
              disabled={!canSubmitWork || loading || isWorkApproved}
              className={`flex items-center gap-2 px-6 py-2 rounded-lg font-medium transition-all
                ${isWorkApproved 
                  ? 'bg-green-100 text-green-700 cursor-default' 
                  : canSubmitWork
                    ? 'bg-blue-600 text-white hover:bg-blue-700 shadow-md hover:shadow-lg'
                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                }`}
            >
              {isWorkApproved ? (
                <>
                  <CheckCircle className="w-4 h-4" />
                  معتمد
                </>
              ) : (
                <>
                  <Send className="w-4 h-4" />
                  اعتماد الأعمال
                </>
              )}
            </button>
          )}

          {/* Exam Submit */}
          {settings.examEnabled && (
            <button
              onClick={() => setShowConfirm('exam')}
              disabled={!canSubmitExam || loading || isExamApproved}
              className={`flex items-center gap-2 px-6 py-2 rounded-lg font-medium transition-all
                ${isExamApproved 
                  ? 'bg-green-100 text-green-700 cursor-default' 
                  : canSubmitExam
                    ? 'bg-blue-600 text-white hover:bg-blue-700 shadow-md hover:shadow-lg'
                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                }`}
            >
              {isExamApproved ? (
                <>
                  <CheckCircle className="w-4 h-4" />
                  معتمد
                </>
              ) : (
                <>
                  <Send className="w-4 h-4" />
                  اعتماد الاختبار
                </>
              )}
            </button>
          )}
        </div>
      </div>

      {/* Confirmation Modal */}
      {showConfirm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
            <div className="flex items-center gap-3 mb-4 text-amber-600">
              <AlertCircle className="w-8 h-8" />
              <h3 className="text-lg font-bold">تأكيد الاعتماد</h3>
            </div>
            <p className="text-gray-600 mb-6 leading-relaxed">
              هل أنت متأكد من اعتماد درجات <span className="font-bold text-gray-800">
                {showConfirm === 'work' ? 'الأعمال' : 'الاختبار'}
              </span>؟
              <br />
              <span className="text-red-500 text-sm">
                بعد الاعتماد لا يمكن التعديل إلا من خلال رئيس القسم أو المدير.
              </span>
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowConfirm(null)}
                className="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
              >
                إلغاء
              </button>
              <button
                onClick={() => {
                  onSubmit(showConfirm);
                  setShowConfirm(null);
                }}
                className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                تأكيد الاعتماد
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default GradeEntryTable;
