using Atlas.SharedKernel.Primitives;

namespace Atlas.BuildingBlocks.Domain.Repositories;

public interface IRepository<TAggregate, TId> : IReadOnlyRepository<TAggregate, TId>
    where TAggregate : AggregateRoot<TId>
    where TId : notnull
{
    Task AddAsync(TAggregate aggregate, CancellationToken cancellationToken = default);

    void Update(TAggregate aggregate);

    void Remove(TAggregate aggregate);
}
