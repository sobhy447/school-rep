namespace Atlas.Modules.Identity.Contracts.UserRoles;

public sealed record AssignRoleRequest(
    long UserId,
    long RoleId,
    long? CompanyId,
    DateTime? ExpiresAtUtc);
