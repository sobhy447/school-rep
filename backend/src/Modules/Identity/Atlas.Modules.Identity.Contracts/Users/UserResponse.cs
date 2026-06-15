namespace Atlas.Modules.Identity.Contracts.Users;

public sealed record UserResponse(
    long UserId,
    long TenantId,
    string Username,
    string? Email,
    string? Phone,
    string? FullNameAr,
    string? FullNameEn,
    long? EmployeeId,
    bool MfaEnabled,
    bool ForcePasswordChange,
    bool IsActive,
    int EntityVersion);
