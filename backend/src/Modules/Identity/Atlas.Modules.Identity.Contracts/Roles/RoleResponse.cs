namespace Atlas.Modules.Identity.Contracts.Roles;

public sealed record RoleResponse(
    long RoleId,
    long? TenantId,
    string RoleCode,
    string NameAr,
    string NameEn,
    bool IsSystemRole,
    bool IsActive);
