namespace Atlas.Modules.Identity.Contracts.Permissions;

public sealed record PermissionResponse(
    long PermissionId,
    string PermissionCode,
    string Module,
    string Action,
    string Scope,
    string? DescriptionAr,
    string? DescriptionEn);
