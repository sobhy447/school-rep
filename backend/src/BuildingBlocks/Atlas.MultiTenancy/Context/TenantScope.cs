namespace Atlas.MultiTenancy.Context;

public sealed class TenantScope : IDisposable
{
    private readonly Action _onDispose;
    private bool _disposed;

    internal TenantScope(Action onDispose) => _onDispose = onDispose;

    public void Dispose()
    {
        if (_disposed)
        {
            return;
        }

        _disposed = true;
        _onDispose();
    }
}
