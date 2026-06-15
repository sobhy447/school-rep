using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Identity.Domain.Permissions;

public sealed class PermissionScope : Enumeration<PermissionScope>
{
    public static readonly PermissionScope Tenant = new(1, "TENANT");
    public static readonly PermissionScope Company = new(2, "COMPANY");
    public static readonly PermissionScope Branch = new(3, "BRANCH");
    public static readonly PermissionScope Department = new(4, "DEPARTMENT");
    public static readonly PermissionScope Own = new(5, "OWN");

    private PermissionScope(int id, string name)
        : base(id, name)
    {
    }
}
