using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Identity.Domain.Permissions;

public sealed class PermissionAction : Enumeration<PermissionAction>
{
    public static readonly PermissionAction View = new(1, "VIEW");
    public static readonly PermissionAction Create = new(2, "CREATE");
    public static readonly PermissionAction Edit = new(3, "EDIT");
    public static readonly PermissionAction Delete = new(4, "DELETE");
    public static readonly PermissionAction Approve = new(5, "APPROVE");
    public static readonly PermissionAction Post = new(6, "POST");
    public static readonly PermissionAction Reverse = new(7, "REVERSE");
    public static readonly PermissionAction Export = new(8, "EXPORT");
    public static readonly PermissionAction Import = new(9, "IMPORT");
    public static readonly PermissionAction Print = new(10, "PRINT");
    public static readonly PermissionAction Assign = new(11, "ASSIGN");
    public static readonly PermissionAction Configure = new(12, "CONFIGURE");
    public static readonly PermissionAction Audit = new(13, "AUDIT");

    private PermissionAction(int id, string name)
        : base(id, name)
    {
    }
}
