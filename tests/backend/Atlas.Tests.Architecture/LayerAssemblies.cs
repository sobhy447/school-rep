using System.Reflection;

namespace Atlas.Tests.Architecture;

internal static class LayerAssemblies
{
    public const string SharedKernel = "Atlas.SharedKernel";
    public const string BuildingBlocksDomain = "Atlas.BuildingBlocks.Domain";
    public const string BuildingBlocksApplication = "Atlas.BuildingBlocks.Application";
    public const string MultiTenancy = "Atlas.MultiTenancy";
    public const string Persistence = "Atlas.Persistence.PostgreSql";
    public const string Caching = "Atlas.Caching.Redis";
    public const string Outbox = "Atlas.Messaging.Outbox";
    public const string Api = "Atlas.Api";
    public const string Modules = "Atlas.Modules";

    public const string TenancyDomain = "Atlas.Modules.Tenancy.Domain";
    public const string TenancyApplication = "Atlas.Modules.Tenancy.Application";
    public const string TenancyContracts = "Atlas.Modules.Tenancy.Contracts";
    public const string TenancyInfrastructure = "Atlas.Modules.Tenancy.Infrastructure";
    public const string TenancyEndpoints = "Atlas.Modules.Tenancy.Endpoints";

    public const string IdentityDomain = "Atlas.Modules.Identity.Domain";
    public const string IdentityApplication = "Atlas.Modules.Identity.Application";
    public const string IdentityContracts = "Atlas.Modules.Identity.Contracts";
    public const string IdentityInfrastructure = "Atlas.Modules.Identity.Infrastructure";
    public const string IdentityEndpoints = "Atlas.Modules.Identity.Endpoints";

    public static readonly Assembly SharedKernelAssembly =
        typeof(Atlas.SharedKernel.Results.Result).Assembly;

    public static readonly Assembly BuildingBlocksDomainAssembly =
        typeof(Atlas.BuildingBlocks.Domain.UnitOfWork.IUnitOfWork).Assembly;

    public static readonly Assembly BuildingBlocksApplicationAssembly =
        typeof(Atlas.BuildingBlocks.Application.DependencyInjection).Assembly;

    public static readonly Assembly MultiTenancyAssembly =
        typeof(Atlas.MultiTenancy.DependencyInjection).Assembly;

    public static readonly Assembly PersistenceAssembly =
        typeof(Atlas.Persistence.PostgreSql.DependencyInjection).Assembly;

    public static readonly Assembly CachingAssembly =
        typeof(Atlas.Caching.Redis.DependencyInjection).Assembly;

    public static readonly Assembly OutboxAssembly =
        typeof(Atlas.Messaging.Outbox.DependencyInjection).Assembly;

    public static readonly Assembly TenancyDomainAssembly =
        typeof(Atlas.Modules.Tenancy.Domain.TenancyErrors).Assembly;

    public static readonly Assembly TenancyApplicationAssembly =
        typeof(Atlas.Modules.Tenancy.Application.DependencyInjection).Assembly;

    public static readonly Assembly TenancyContractsAssembly =
        typeof(Atlas.Modules.Tenancy.Contracts.Tenants.TenantResponse).Assembly;

    public static readonly Assembly TenancyInfrastructureAssembly =
        typeof(Atlas.Modules.Tenancy.Infrastructure.DependencyInjection).Assembly;

    public static readonly Assembly IdentityDomainAssembly =
        typeof(Atlas.Modules.Identity.Domain.IdentityErrors).Assembly;

    public static readonly Assembly IdentityApplicationAssembly =
        typeof(Atlas.Modules.Identity.Application.DependencyInjection).Assembly;

    public static readonly Assembly IdentityContractsAssembly =
        typeof(Atlas.Modules.Identity.Contracts.Users.UserResponse).Assembly;

    public static readonly Assembly IdentityInfrastructureAssembly =
        typeof(Atlas.Modules.Identity.Infrastructure.DependencyInjection).Assembly;

    public static readonly IReadOnlyList<Assembly> AllLibraries =
    [
        SharedKernelAssembly,
        BuildingBlocksDomainAssembly,
        BuildingBlocksApplicationAssembly,
        MultiTenancyAssembly,
        PersistenceAssembly,
        CachingAssembly,
        OutboxAssembly,
        TenancyDomainAssembly,
        TenancyApplicationAssembly,
        TenancyContractsAssembly,
        TenancyInfrastructureAssembly,
        IdentityDomainAssembly,
        IdentityApplicationAssembly,
        IdentityContractsAssembly,
        IdentityInfrastructureAssembly
    ];
}
