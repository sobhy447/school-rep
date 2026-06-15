namespace Atlas.Modules.Identity.Contracts.RolePermissions;

public sealed record RolePermissionResponse(
    long RolePermissionId,
    long TenantId,
    long RoleId,
    long PermissionId);
