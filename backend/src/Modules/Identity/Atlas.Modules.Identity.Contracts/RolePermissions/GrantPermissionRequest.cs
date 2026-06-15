namespace Atlas.Modules.Identity.Contracts.RolePermissions;

public sealed record GrantPermissionRequest(
    long RoleId,
    long PermissionId);
