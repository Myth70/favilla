# Avatar del cast demo

Metti qui le **10 foto avatar** del cast "Aurora Studio" (JPG quadrate, volto
centrato, ~512px). Il `DemoSeeder` le copia in `public/uploads/avatars/` e
`users.avatar_path` è già impostato in `database/seeds/test_users.sql`.

La copia è **tollerante**: se un file manca, il seed prosegue (l'avatar appare
appena il file è presente; rilancia con `php favilla demo:seed --force`).

Nomi file richiesti (esatti):

```
demo-avatar-giuliaferrari.jpg    (IT, donna, ~44)
demo-avatar-jameswhitaker.jpg    (EN, uomo,  ~52)
demo-avatar-annaweber.jpg        (DE, donna, ~39)
demo-avatar-marcobianchi.jpg     (IT, uomo,  ~31)
demo-avatar-emilyturner.jpg      (EN, donna, ~35)
demo-avatar-julienmoreau.jpg     (FR, uomo,  ~47)
demo-avatar-camillelaurent.jpg   (FR, donna, ~33)
demo-avatar-lukasbraun.jpg       (DE, uomo,  ~58)
demo-avatar-diegoherrera.jpg     (ES, uomo,  ~41)
demo-avatar-luciaromero.jpg      (ES, donna, ~30)
```
