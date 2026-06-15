using Atlas.Modules.Identity.Domain.Permissions;
using Atlas.Modules.Identity.Domain.RolePermissions;
using Atlas.Modules.Identity.Domain.Roles;
using Atlas.Modules.Identity.Domain.UserRoles;
using Atlas.Modules.Identity.Domain.Users;
using Atlas.Modules.Identity.Infrastructure.Configurations;
using Atlas.MultiTenancy.Abstractions;
using Atlas.Persistence.PostgreSql.Context;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Modules.Identity.Infrastructure.Persistence;

public sealed class IdentityDbContext : AtlasDbContextBase
{
    public IdentityDbContext(DbContextOptions<IdentityDbContext> options, ITenantContext tenantContext)
        : base(options, tenantContext)
    {
    }

    protected override string SchemaName => "atlas";

    public DbSet<User> Users => Set<User>();

    public DbSet<Role> Roles => Set<Role>();

    public DbSet<Permission> Permissions => Set<Permission>();

    public DbSet<UserRole> UserRoles => Set<UserRole>();

    public DbSet<RolePermission> RolePermissions => Set<RolePermission>();

    protected override void ConfigureModel(ModelBuilder modelBuilder)
    {
        modelBuilder.ApplyConfiguration(new UserConfiguration());
        modelBuilder.ApplyConfiguration(new RoleConfiguration());
        modelBuilder.ApplyConfiguration(new PermissionConfiguration());
        modelBuilder.ApplyConfiguration(new UserRoleConfiguration());
        modelBuilder.ApplyConfiguration(new RolePermissionConfiguration());
    }
}
