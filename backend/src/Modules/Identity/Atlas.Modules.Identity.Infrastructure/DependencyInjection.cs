using Atlas.Modules.Identity.Application.Abstractions;
using Atlas.Modules.Identity.Domain.Permissions;
using Atlas.Modules.Identity.Domain.RolePermissions;
using Atlas.Modules.Identity.Domain.Roles;
using Atlas.Modules.Identity.Domain.UserRoles;
using Atlas.Modules.Identity.Domain.Users;
using Atlas.Modules.Identity.Infrastructure.Persistence;
using Atlas.Modules.Identity.Infrastructure.Security;
using Atlas.Persistence.PostgreSql;
using Microsoft.Extensions.DependencyInjection;

namespace Atlas.Modules.Identity.Infrastructure;

public static class DependencyInjection
{
    public static IServiceCollection AddIdentityInfrastructure(this IServiceCollection services)
    {
        services.AddAtlasDbContext<IdentityDbContext>();

        services.AddSingleton<IPasswordHasher, BCryptPasswordHasher>();

        services.AddScoped<IUserRepository, UserRepository>();
        services.AddScoped<IRoleRepository, RoleRepository>();
        services.AddScoped<IPermissionRepository, PermissionRepository>();
        services.AddScoped<IUserRoleRepository, UserRoleRepository>();
        services.AddScoped<IRolePermissionRepository, RolePermissionRepository>();

        return services;
    }
}
