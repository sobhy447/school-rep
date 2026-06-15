namespace Atlas.Modules.Identity.Contracts.Roles;

public sealed record CreateRoleRequest(
    string RoleCode,
    string NameAr,
    string NameEn);
