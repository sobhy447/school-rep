namespace Atlas.Modules.Identity.Contracts.Permissions;

public sealed record CreatePermissionRequest(
    string PermissionCode,
    string Module,
    string Action,
    string Scope,
    string? DescriptionAr,
    string? DescriptionEn);
