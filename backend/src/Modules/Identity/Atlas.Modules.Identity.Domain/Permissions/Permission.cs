using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Identity.Domain.Permissions;

public sealed class Permission : AggregateRoot<long>
{
    public const int CodeMaxLength = 200;
    public const int ModuleMaxLength = 100;

    private Permission()
    {
        PermissionCode = null!;
        Module = null!;
        Action = null!;
        Scope = null!;
    }

    public string PermissionCode { get; private set; }

    public string Module { get; private set; }

    public PermissionAction Action { get; private set; }

    public PermissionScope Scope { get; private set; }

    public string? DescriptionAr { get; private set; }

    public string? DescriptionEn { get; private set; }

    public DateTime CreatedAtUtc { get; private set; }

    public long CreatedBy { get; private set; }

    public static Permission Create(
        string permissionCode,
        string module,
        PermissionAction action,
        PermissionScope scope,
        string? descriptionAr,
        string? descriptionEn,
        long createdBy)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(permissionCode);
        ArgumentException.ThrowIfNullOrWhiteSpace(module);
        ArgumentNullException.ThrowIfNull(action);
        ArgumentNullException.ThrowIfNull(scope);
        ArgumentOutOfRangeException.ThrowIfGreaterThan(permissionCode.Trim().Length, CodeMaxLength);
        ArgumentOutOfRangeException.ThrowIfGreaterThan(module.Trim().Length, ModuleMaxLength);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(createdBy);

        return new Permission
        {
            PermissionCode = permissionCode.Trim(),
            Module = module.Trim(),
            Action = action,
            Scope = scope,
            DescriptionAr = string.IsNullOrWhiteSpace(descriptionAr) ? null : descriptionAr.Trim(),
            DescriptionEn = string.IsNullOrWhiteSpace(descriptionEn) ? null : descriptionEn.Trim(),
            CreatedBy = createdBy
        };
    }
}
