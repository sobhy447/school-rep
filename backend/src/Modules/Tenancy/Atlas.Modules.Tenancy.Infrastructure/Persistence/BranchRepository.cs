using Atlas.Modules.Tenancy.Domain.Branches;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Modules.Tenancy.Infrastructure.Persistence;

internal sealed class BranchRepository(TenancyDbContext context)
    : RepositoryBase<Branch>(context), IBranchRepository
{
    public Task<bool> ExistsByCodeAsync(
        long tenantId,
        long companyId,
        string branchCode,
        CancellationToken cancellationToken = default) =>
        Set.AnyAsync(
            branch => branch.TenantId == tenantId &&
                      branch.CompanyId == companyId &&
                      branch.BranchCode == branchCode,
            cancellationToken);

    public async Task<IReadOnlyList<Branch>> ListByCompanyAsync(
        long tenantId,
        long companyId,
        CancellationToken cancellationToken = default) =>
        await Set.Where(branch => branch.TenantId == tenantId && branch.CompanyId == companyId)
            .OrderBy(branch => branch.BranchCode)
            .ToListAsync(cancellationToken);
}
