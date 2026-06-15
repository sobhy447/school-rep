using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Persistence.PostgreSql.Context;

namespace Atlas.Api.Infrastructure;

internal sealed class CompositeUnitOfWork(IEnumerable<AtlasDbContextBase> contexts) : IUnitOfWork
{
    public async Task<int> SaveChangesAsync(CancellationToken cancellationToken = default)
    {
        int affected = 0;

        foreach (AtlasDbContextBase context in contexts)
        {
            affected += await context.SaveChangesAsync(cancellationToken);
        }

        return affected;
    }
}
