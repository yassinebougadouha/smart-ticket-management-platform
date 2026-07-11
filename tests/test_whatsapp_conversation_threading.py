from types import SimpleNamespace

from app.services.whatsapp_service import WhatsAppSyncService, normalize_whatsapp_number


class FakeResult:
    def __init__(self, value=None):
        self._value = value

    def scalar_one_or_none(self):
        return self._value

    def scalars(self):
        return self

    def all(self):
        return self._value if isinstance(self._value, list) else [self._value]


class FakeDB:
    def __init__(self, users):
        self.users = users
        self.calls = 0

    def execute(self, stmt):
        self.calls += 1
        if self.calls == 1:
            return FakeResult(None)
        if self.calls == 2:
            return FakeResult(None)
        if self.calls == 3:
            return FakeResult(None)
        return FakeResult(self.users)

    def flush(self):
        return None

    def add(self, obj):
        return None


def test_normalize_whatsapp_number_handles_international_variants():
    assert normalize_whatsapp_number("+21611122233") == "21611122233"
    assert normalize_whatsapp_number("0021611122233") == "21611122233"
    assert normalize_whatsapp_number("21611122233@c.us") == "21611122233"


def test_inbound_and_outbound_can_reuse_existing_user_by_normalized_phone():
    existing_user = SimpleNamespace(phone_number="+21611122233")
    db = FakeDB([existing_user])
    svc = WhatsAppSyncService(db)

    matched_user = svc._find_existing_user_by_phone("21611122233@c.us")

    assert matched_user is existing_user
    assert db.calls >= 1
