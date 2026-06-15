using Atlas.Modules.Identity.Contracts.Permissions;
using Atlas.Modules.Identity.Contracts.RolePermissions;
using Atlas.Modules.Identity.Contracts.Roles;
using Atlas.Modules.Identity.Contracts.UserRoles;
using Atlas.Modules.Identity.Contracts.Users;
using Atlas.Modules.Identity.Domain.Permissions;
using Atlas.Modules.Identity.Domain.RolePermissions;
using Atlas.Modules.Identity.Domain.Roles;
using Atlas.Modules.Identity.Domain.UserRoles;
using Atlas.Modules.Identity.Domain.Users;

namespace Atlas.Modules.Identity.Application.Common;

internal static class IdentityMapper
{
    public static UserResponse ToResponse(User user) =>
        new(
            user.Id,
            user.TenantId,
            user.Username.Value,
            user.Email?.Value,
            user.Phone,
            user.FullNameAr,
            user.FullNameEn,
            user.EmployeeId,
            user.MfaEnabled,
            user.ForcePasswordChange,
            user.IsActive,
            user.EntityVersion);

    public static RoleResponse ToResponse(Role role) =>
        new(
            role.Id,
            role.TenantId,
            role.RoleCode,
            role.Name.Arabic,
            role.Name.English,
            role.IsSystemRole,
            role.IsActive);

    public static PermissionResponse ToResponse(Permission permission) =>
        new(
            permission.Id,
            permission.PermissionCode,
            permission.Module,
            permission.Action.Name,
            permission.Scope.Name,
            permission.DescriptionAr,
            permission.DescriptionEn);

    public static UserRoleResponse ToResponse(UserRole userRole) =>
        new(
            userRole.Id,
            userRole.TenantId,
            userRole.UserId,
            userRole.RoleId,
            userRole.CompanyId,
            userRole.AssignedAtUtc,
            userRole.AssignedBy,
            userRole.ExpiresAtUtc);

    public static RolePermissionResponse ToResponse(RolePermission rolePermission) =>
        new(
            rolePermission.Id,
            rolePermission.TenantId,
            rolePermission.RoleId,
            rolePermission.PermissionId);
}
