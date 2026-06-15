using Microsoft.AspNetCore.Routing;

namespace Atlas.Modules.Tenancy.Endpoints;

public static class TenancyEndpoints
{
    public static IEndpointRouteBuilder MapTenancyEndpoints(this IEndpointRouteBuilder builder)
    {
        builder.MapTenantEndpoints();
        builder.MapCompanyEndpoints();
        builder.MapBranchEndpoints();
        return builder;
    }
}
