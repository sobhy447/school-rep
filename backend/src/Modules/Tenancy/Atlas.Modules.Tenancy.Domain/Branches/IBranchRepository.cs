using Atlas.BuildingBlocks.Domain.Repositories;

namespace Atlas.Modules.Tenancy.Domain.Branches;

public interface IBranchRepository : IRepository<Branch, long>
{
    Task<bool> ExistsByCodeAsync(
        long tenantId,
        long companyId,
        string branchCode,
        CancellationToken cancellationToken = default);

    Task<IReadOnlyList<Branch>> ListByCompanyAsync(
        long tenantId,
        long companyId,
        CancellationToken cancellationToken = default);
}
