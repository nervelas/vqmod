#!/usr/bin/env python3
"""Verificación independiente de .ics con las bibliotecas icalendar y recurring-ical-events (solo pruebas).
  ics_check.py validate ARCHIVO            -> JSON con los componentes leídos
  ics_check.py expand ARCHIVO DESDE HASTA  -> JSON [[inicio, fin], ...] (timestamps UTC) de eventos que ocupan tiempo"""
import datetime as dt, json, sys
from icalendar import Calendar
import recurring_ical_events

mode, path = sys.argv[1], sys.argv[2]
cal = Calendar.from_ical(open(path, 'rb').read())

def ts(v, default_tz=dt.timezone.utc):
    if isinstance(v, dt.datetime):
        if v.tzinfo is None:
            v = v.replace(tzinfo=default_tz)
        return int(v.timestamp())
    return int(dt.datetime(v.year, v.month, v.day, tzinfo=default_tz).timestamp())

if mode == 'validate':
    out = {'method': str(cal.get('METHOD')), 'version': str(cal.get('VERSION')), 'prodid': str(cal.get('PRODID')), 'events': []}
    for e in cal.walk('VEVENT'):
        out['events'].append({
            'uid': str(e.get('UID')), 'summary': str(e.get('SUMMARY')), 'description': str(e.get('DESCRIPTION')),
            'location': str(e.get('LOCATION', '')), 'status': str(e.get('STATUS')), 'sequence': int(e.get('SEQUENCE', 0)),
            'start': ts(e.decoded('DTSTART')), 'end': ts(e.decoded('DTEND')), 'url': str(e.get('URL', '')),
            'organizer': str(e.get('ORGANIZER', '')), 'alarms': len(list(e.walk('VALARM'))),
        })
    print(json.dumps(out))
else:
    start = dt.datetime.fromtimestamp(int(sys.argv[3]), dt.timezone.utc)
    end = dt.datetime.fromtimestamp(int(sys.argv[4]), dt.timezone.utc)
    res = []
    for e in recurring_ical_events.of(cal).between(start, end):
        if str(e.get('STATUS', '')).upper() == 'CANCELLED' or str(e.get('TRANSP', '')).upper() == 'TRANSPARENT':
            continue
        s = e.decoded('DTSTART')
        en = e.decoded('DTEND') if e.get('DTEND') else None
        tz = dt.timezone.utc
        res.append([ts(s), ts(en)])
    print(json.dumps(sorted(res)))
