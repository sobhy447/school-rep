using System.Reflection;
using FluentAssertions;
using NetArchTest.Rules;
using Xunit;

namespace Atlas.Tests.Architecture;

public sealed class DependencyRuleTests
{
    [Fact]
    public void SharedKernel_ShouldNotDependOnAnyOtherLayer()
    {
        AssertNoDependency(
            LayerAssemblies.SharedKernelAssembly,
            LayerAssemblies.MultiTenancy,
            LayerAssemblies.BuildingBlocksApplication,
            LayerAssemblies.Persistence,
            LayerAssemblies.Caching,
            LayerAssemblies.Outbox,
            LayerAssemblies.Modules,
            LayerAssemblies.Api);
    }

    [Fact]
    public void BuildingBlocksDomain_ShouldDependOnSharedKernelOnly()
    {
        AssertNoDependency(
            LayerAssemblies.BuildingBlocksDomainAssembly,
            LayerAssemblies.BuildingBlocksApplication,
            LayerAssemblies.MultiTenancy,
            LayerAssemblies.Persistence,
            LayerAssemblies.Caching,
            LayerAssemblies.Outbox,
            LayerAssemblies.Modules,
            LayerAssemblies.Api);
    }

    [Fact]
    public void BuildingBlocksApplication_ShouldNotDependOnInfrastructureOrModules()
    {
        AssertNoDependency(
            LayerAssemblies.BuildingBlocksApplicationAssembly,
            LayerAssemblies.MultiTenancy,
            LayerAssemblies.Persistence,
            LayerAssemblies.Caching,
            LayerAssemblies.Outbox,
            LayerAssemblies.Modules,
            LayerAssemblies.Api);
    }

    [Fact]
    public void Persistence_ShouldNotDependOnModulesOrApi()
    {
        AssertNoDependency(LayerAssemblies.PersistenceAssembly, LayerAssemblies.Modules, LayerAssemblies.Api);
    }

    [Fact]
    public void Caching_ShouldNotDependOnModulesOrApi()
    {
        AssertNoDependency(LayerAssemblies.CachingAssembly, LayerAssemblies.Modules, LayerAssemblies.Api);
    }

    [Fact]
    public void ModuleDomain_ShouldNotDependOnApplicationInfrastructureOrEndpoints()
    {
        AssertNoDependency(
            LayerAssemblies.TenancyDomainAssembly,
            LayerAssemblies.TenancyApplication,
            LayerAssemblies.TenancyInfrastructure,
            LayerAssemblies.TenancyEndpoints,
            LayerAssemblies.Persistence,
            LayerAssemblies.Caching,
            LayerAssemblies.Api);

        AssertNoDependency(
            LayerAssemblies.IdentityDomainAssembly,
            LayerAssemblies.IdentityApplication,
            LayerAssemblies.IdentityInfrastructure,
            LayerAssemblies.IdentityEndpoints,
            LayerAssemblies.Persistence,
            LayerAssemblies.Caching,
            LayerAssemblies.Api);
    }

    [Fact]
    public void ModuleApplication_ShouldNotDependOnInfrastructureEndpointsOrPersistence()
    {
        AssertNoDependency(
            LayerAssemblies.TenancyApplicationAssembly,
            LayerAssemblies.TenancyInfrastructure,
            LayerAssemblies.TenancyEndpoints,
            LayerAssemblies.Persistence,
            LayerAssemblies.Caching,
            LayerAssemblies.Api);

        AssertNoDependency(
            LayerAssemblies.IdentityApplicationAssembly,
            LayerAssemblies.IdentityInfrastructure,
            LayerAssemblies.IdentityEndpoints,
            LayerAssemblies.Persistence,
            LayerAssemblies.Caching,
            LayerAssemblies.Api);
    }

    [Fact]
    public void ModuleContracts_ShouldNotDependOnDomainApplicationOrBuildingBlocks()
    {
        AssertNoDependency(
            LayerAssemblies.TenancyContractsAssembly,
            LayerAssemblies.TenancyDomain,
            LayerAssemblies.TenancyApplication,
            "Atlas.BuildingBlocks",
            LayerAssemblies.Persistence,
            LayerAssemblies.Api);

        AssertNoDependency(
            LayerAssemblies.IdentityContractsAssembly,
            LayerAssemblies.IdentityDomain,
            LayerAssemblies.IdentityApplication,
            "Atlas.BuildingBlocks",
            LayerAssemblies.Persistence,
            LayerAssemblies.Api);
    }

    [Fact]
    public void ModuleInfrastructure_ShouldNotDependOnEndpointsOrApi()
    {
        AssertNoDependency(
            LayerAssemblies.TenancyInfrastructureAssembly,
            LayerAssemblies.TenancyEndpoints,
            LayerAssemblies.Api);

        AssertNoDependency(
            LayerAssemblies.IdentityInfrastructureAssembly,
            LayerAssemblies.IdentityEndpoints,
            LayerAssemblies.Api);
    }

    [Fact]
    public void Modules_ShouldNotDependOnEachOther()
    {
        AssertNoDependency(LayerAssemblies.TenancyDomainAssembly, "Atlas.Modules.Identity");
        AssertNoDependency(LayerAssemblies.TenancyApplicationAssembly, "Atlas.Modules.Identity");
        AssertNoDependency(LayerAssemblies.TenancyInfrastructureAssembly, "Atlas.Modules.Identity");

        AssertNoDependency(LayerAssemblies.IdentityDomainAssembly, "Atlas.Modules.Tenancy");
        AssertNoDependency(LayerAssemblies.IdentityApplicationAssembly, "Atlas.Modules.Tenancy");
        AssertNoDependency(LayerAssemblies.IdentityInfrastructureAssembly, "Atlas.Modules.Tenancy");
    }

    [Fact]
    public void NoLibrary_ShouldDependOnApi()
    {
        foreach (Assembly assembly in LayerAssemblies.AllLibraries)
        {
            AssertNoDependency(assembly, LayerAssemblies.Api);
        }
    }

    private static void AssertNoDependency(Assembly assembly, params string[] forbiddenNamespaces)
    {
        TestResult result = Types.InAssembly(assembly)
            .ShouldNot()
            .HaveDependencyOnAny(forbiddenNamespaces)
            .GetResult();

        result.IsSuccessful.Should().BeTrue(
            because: $"{assembly.GetName().Name} must not depend on [{string.Join(", ", forbiddenNamespaces)}], " +
                     $"but these types do: {string.Join(", ", result.FailingTypeNames ?? Array.Empty<string>())}");
    }
}
