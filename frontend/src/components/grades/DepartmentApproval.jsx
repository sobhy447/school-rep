import React, { useState } from 'react';
import { CheckCircle, AlertCircle, Send, Lock, Users } from 'lucide-react';

const DepartmentApproval = ({ 
  classes, 
  canApprove, 
  onApproveWork, 
  onApproveExam, 
  approvalStatus,
  loading 
}) => {
  const [showConfirm, setShowConfirm] = useState(null);

  // Calculate statistics
  const totalClasses = classes.length;
  const workApprovedCount = classes.filter(c => c.workApproved).length;
  const examApprovedCount = classes.filter(c => c.examApproved).length;

  const allWorkApproved = workApprovedCount === totalClasses && totalClasses > 0;
  const allExamApproved = examApprovedCount === totalClasses && totalClasses > 0;

  const isWorkDeptApproved = approvalStatus?.work === 'approved';
  const isExamDeptApproved = approvalStatus?.exam === 'approved';

  const handleApprove = async (type) => {
    setShowConfirm(null);
    if (type === 'work') {
      await onApproveWork();
    } else {
      await onApproveExam();
    }
  };

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center">
          <Users className="w-6 h-6 text-white" />
        </div>
        <div>
          <h2 className="text-lg font-bold text-gray-800">اعتماد القسم</h2>
          <p className="text-sm text-gray-500">اعتماد جميع درجات قسمك بالكامل</p>
        </div>
      </div>

      {/* Statistics */}
      <div className="grid grid-cols-2 gap-4 mb-6">
        <div className={`p-4 rounded-xl border-2 ${allWorkApproved ? 'border-green-300 bg-green-50' : 'border-yellow-300 bg-yellow-50'}`}>
          <div className="flex items-center justify-between mb-2">
            <span className="font-medium text-gray-700">الأعمال</span>
            <span className={`text-lg font-bold ${allWorkApproved ? 'text-green-700' : 'text-yellow-700'}`}>
              {workApprovedCount}/{totalClasses}
            </span>
          </div>
          <div className="w-full bg-gray-200 rounded-full h-2">
            <div 
              className={`h-2 rounded-full transition-all ${allWorkApproved ? 'bg-green-500' : 'bg-yellow-500'}`}
              style={{ width: `${totalClasses > 0 ? (workApprovedCount / totalClasses) * 100 : 0}%` }}
            ></div>
          </div>
          <p className="text-xs mt-2 text-gray-500">
            {allWorkApproved ? 'جميع المعلمين اعتمدوا' : `${totalClasses - workApprovedCount} فصول باقية`}
          </p>
        </div>

        <div className={`p-4 rounded-xl border-2 ${allExamApproved ? 'border-green-300 bg-green-50' : 'border-yellow-300 bg-yellow-50'}`}>
          <div className="flex items-center justify-between mb-2">
            <span className="font-medium text-gray-700">الاختبار</span>
            <span className={`text-lg font-bold ${allExamApproved ? 'text-green-700' : 'text-yellow-700'}`}>
              {examApprovedCount}/{totalClasses}
            </span>
          </div>
          <div className="w-full bg-gray-200 rounded-full h-2">
            <div 
              className={`h-2 rounded-full transition-all ${allExamApproved ? 'bg-green-500' : 'bg-yellow-500'}`}
              style={{ width: `${totalClasses > 0 ? (examApprovedCount / totalClasses) * 100 : 0}%` }}
            ></div>
          </div>
          <p className="text-xs mt-2 text-gray-500">
            {allExamApproved ? 'جميع المعلمين اعتمدوا' : `${totalClasses - examApprovedCount} فصول باقية`}
          </p>
        </div>
      </div>

      {/* Approval Buttons */}
      <div className="flex gap-4">
        {/* Work Approval */}
        <div className="flex-1">
          {isWorkDeptApproved ? (
            <div className="flex items-center justify-center gap-2 px-4 py-3 bg-green-100 text-green-700 rounded-lg font-medium">
              <CheckCircle className="w-5 h-5" />
              تم اعتماد الأعمال للقسم
            </div>
          ) : (
            <button
              onClick={() => setShowConfirm('work')}
              disabled={!allWorkApproved || loading}
              className={`w-full flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-medium transition-all
                ${allWorkApproved 
                  ? 'bg-purple-600 text-white hover:bg-purple-700 shadow-md hover:shadow-lg' 
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                }
              `}
            >
              {allWorkApproved ? (
                <>
                  <Send className="w-5 h-5" />
                  اعتماد الأعمال للقسم
                </>
              ) : (
                <>
                  <Lock className="w-5 h-5" />
                  انتظار اعتماد المعلمين
                </>
              )}
            </button>
          )}
        </div>

        {/* Exam Approval */}
        <div className="flex-1">
          {isExamDeptApproved ? (
            <div className="flex items-center justify-center gap-2 px-4 py-3 bg-green-100 text-green-700 rounded-lg font-medium">
              <CheckCircle className="w-5 h-5" />
              تم اعتماد الاختبار للقسم
            </div>
          ) : (
            <button
              onClick={() => setShowConfirm('exam')}
              disabled={!allExamApproved || loading}
              className={`w-full flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-medium transition-all
                ${allExamApproved 
                  ? 'bg-purple-600 text-white hover:bg-purple-700 shadow-md hover:shadow-lg' 
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                }
              `}
            >
              {allExamApproved ? (
                <>
                  <Send className="w-5 h-5" />
                  اعتماد الاختبار للقسم
                </>
              ) : (
                <>
                  <Lock className="w-5 h-5" />
                  انتظار اعتماد المعلمين
                </>
              )}
            </button>
          )}
        </div>
      </div>

      {/* Info */}
      <div className="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
        <div className="flex items-start gap-2">
          <AlertCircle className="w-5 h-5 text-blue-600 mt-0.5" />
          <div className="text-sm text-blue-800">
            <p className="font-medium mb-1">تعليمات الاعتماد:</p>
            <ul className="list-disc list-inside space-y-1 text-blue-700">
              <li>لا يمكن اعتماد القسم إلا بعد اعتماد جميع المعلمين</li>
              <li>بعد اعتماد القسم، يتم إرسال إشعار للمدير</li>
              <li>لا يمكن التعديل بعد اعتماد القسم إلا من خلال المدير</li>
            </ul>
          </div>
        </div>
      </div>

      {/* Confirm Modal */}
      {showConfirm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
            <div className="flex items-center gap-3 mb-4 text-purple-600">
              <AlertCircle className="w-8 h-8" />
              <h3 className="text-lg font-bold">تأكيد اعتماد القسم</h3>
            </div>
            <p className="text-gray-600 mb-6 leading-relaxed">
              هل أنت متأكد من اعتماد درجات <span className="font-bold text-gray-800">
                {showConfirm === 'work' ? 'الأعمال' : 'الاختبار'}
              </span> للقسم بالكامل؟
              <br /><br />
              <span className="text-red-500 text-sm">
                بعد الاعتماد لا يمكن التعديل إلا من خلال المدير.
                <br />
                سيتم إرسال إشعار للمدير باعتماد القسم.
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
                onClick={() => handleApprove(showConfirm)}
                className="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors"
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

export default DepartmentApproval;
