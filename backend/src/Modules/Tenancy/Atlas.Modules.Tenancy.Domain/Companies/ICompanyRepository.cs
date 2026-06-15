using Atlas.BuildingBlocks.Domain.Repositories;

namespace Atlas.Modules.Tenancy.Domain.Companies;

public interface ICompanyRepository : IRepository<Company, long>
{
    Task<bool> ExistsByCodeAsync(long tenantId, string companyCode, CancellationToken cancellationToken = default);

    Task<int> CountByTenantAsync(long tenantId, CancellationToken cancellationToken = default);

    Task<IReadOnlyList<Company>> ListByTenantAsync(long tenantId, CancellationToken cancellationToken = default);
}
