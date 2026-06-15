using Microsoft.AspNetCore.Routing;

namespace Atlas.Modules.Identity.Endpoints;

public static class IdentityEndpoints
{
    public static IEndpointRouteBuilder MapIdentityEndpoints(this IEndpointRouteBuilder builder)
    {
        builder.MapUserEndpoints();
        builder.MapRoleEndpoints();
        builder.MapPermissionEndpoints();
        builder.MapUserRoleEndpoints();
        return builder;
    }
}
