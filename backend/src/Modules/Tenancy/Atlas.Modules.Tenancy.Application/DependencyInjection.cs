using Atlas.BuildingBlocks.Application;
using Microsoft.Extensions.DependencyInjection;

namespace Atlas.Modules.Tenancy.Application;

public static class DependencyInjection
{
    public static IServiceCollection AddTenancyApplication(this IServiceCollection services) =>
        services.AddApplication(typeof(DependencyInjection).Assembly);
}
