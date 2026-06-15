using Atlas.BuildingBlocks.Application;
using Microsoft.Extensions.DependencyInjection;

namespace Atlas.Modules.Identity.Application;

public static class DependencyInjection
{
    public static IServiceCollection AddIdentityApplication(this IServiceCollection services) =>
        services.AddApplication(typeof(DependencyInjection).Assembly);
}
