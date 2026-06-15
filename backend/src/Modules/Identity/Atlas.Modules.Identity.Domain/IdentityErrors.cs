using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Identity.Domain;

public static class IdentityErrors
{
    public static Error UserNotFound(long userId) =>
        Error.NotFound("Identity.User.NotFound", $"User '{userId}' was not found.");

    public static Error UsernameAlreadyExists(string username) =>
        Error.Conflict("Identity.User.UsernameAlreadyExists", $"A user with username '{username}' already exists.");

    public static Error EmailAlreadyExists(string email) =>
        Error.Conflict("Identity.User.EmailAlreadyExists", $"A user with email '{email}' already exists.");

    public static Error RoleNotFound(long roleId) =>
        Error.NotFound("Identity.Role.NotFound", $"Role '{roleId}' was not found.");

    public static Error RoleCodeAlreadyExists(string roleCode) =>
        Error.Conflict("Identity.Role.CodeAlreadyExists", $"A role with code '{roleCode}' already exists.");

    public static Error PermissionNotFound(long permissionId) =>
        Error.NotFound("Identity.Permission.NotFound", $"Permission '{permissionId}' was not found.");

    public static Error PermissionCodeAlreadyExists(string permissionCode) =>
        Error.Conflict("Identity.Permission.CodeAlreadyExists", $"A permission with code '{permissionCode}' already exists.");

    public static Error PermissionActionInvalid(string action) =>
        Error.Validation("Identity.Permission.ActionInvalid", $"Permission action '{action}' is not valid.");

    public static Error PermissionScopeInvalid(string scope) =>
        Error.Validation("Identity.Permission.ScopeInvalid", $"Permission scope '{scope}' is not valid.");

    public static Error RoleAlreadyAssigned() =>
        Error.Conflict("Identity.UserRole.AlreadyAssigned", "The role is already assigned to the user for this scope.");

    public static Error PermissionAlreadyGranted() =>
        Error.Conflict("Identity.RolePermission.AlreadyGranted", "The permission is already granted to the role.");
}
