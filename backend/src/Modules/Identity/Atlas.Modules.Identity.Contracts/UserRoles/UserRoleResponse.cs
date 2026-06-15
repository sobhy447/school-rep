namespace Atlas.Modules.Identity.Contracts.UserRoles;

public sealed record UserRoleResponse(
    long UserRoleId,
    long TenantId,
    long UserId,
    long RoleId,
    long? CompanyId,
    DateTime AssignedAtUtc,
    long AssignedBy,
    DateTime? ExpiresAtUtc);
