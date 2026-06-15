namespace Atlas.Modules.Identity.Contracts.Users;

public sealed record CreateUserRequest(
    string Username,
    string? Email,
    string Password,
    string? Phone,
    string? FullNameAr,
    string? FullNameEn,
    long? EmployeeId,
    bool ForcePasswordChange);
