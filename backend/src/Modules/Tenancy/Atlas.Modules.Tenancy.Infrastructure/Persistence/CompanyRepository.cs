using Atlas.Modules.Tenancy.Domain.Companies;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Modules.Tenancy.Infrastructure.Persistence;

internal sealed class CompanyRepository(TenancyDbContext context)
    : RepositoryBase<Company>(context), ICompanyRepository
{
    public Task<bool> ExistsByCodeAsync(long tenantId, string companyCode, CancellationToken cancellationToken = default) =>
        Set.AnyAsync(company => company.TenantId == tenantId && company.CompanyCode == companyCode, cancellationToken);

    public Task<int> CountByTenantAsync(long tenantId, CancellationToken cancellationToken = default) =>
        Set.CountAsync(company => company.TenantId == tenantId, cancellationToken);

    public async Task<IReadOnlyList<Company>> ListByTenantAsync(long tenantId, CancellationToken cancellationToken = default) =>
        await Set.Where(company => company.TenantId == tenantId)
            .OrderBy(company => company.CompanyCode)
            .ToListAsync(cancellationToken);
}
