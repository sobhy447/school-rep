using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Identity.Domain.ValueObjects;

public sealed class LocalizedText : ValueObject
{
    public const int MaxLength = 300;

    private LocalizedText(string arabic, string english)
    {
        Arabic = arabic;
        English = english;
    }

    public string Arabic { get; }

    public string English { get; }

    public static LocalizedText Create(string arabic, string english)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(arabic);
        ArgumentException.ThrowIfNullOrWhiteSpace(english);

        string trimmedArabic = arabic.Trim();
        string trimmedEnglish = english.Trim();

        ArgumentOutOfRangeException.ThrowIfGreaterThan(trimmedArabic.Length, MaxLength);
        ArgumentOutOfRangeException.ThrowIfGreaterThan(trimmedEnglish.Length, MaxLength);

        return new LocalizedText(trimmedArabic, trimmedEnglish);
    }

    protected override IEnumerable<object?> GetEqualityComponents()
    {
        yield return Arabic;
        yield return English;
    }
}
