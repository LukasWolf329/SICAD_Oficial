import "../../../../style/global.css";

import React, { useEffect, useState } from 'react';
import { Text, View, Image, ScrollView, Pressable, TextInput } from 'react-native';
import { Feather, Ionicons } from '@expo/vector-icons';

import { Mainframe, NavBar, SideBar, SideBarCategory } from '../../../../components/NavBar';
import { CertifyBox, InfoBox, ParticipantCertifyBox, PeopleBox } from "@/components/InfoBox";
import { router } from "expo-router";

type Certificado = {
  cod_certificado: number;
  participante: string;
  email: string;
  status: number;
};


export default function SendCerticate() {
  const [certificados, setCertificados] = useState<Certificado[]>([]);
  useEffect(() => {
    fetch(`http://192.168.1.9/SICAD/get_certificado.php?t=${Date.now()}`) // para nao carregar o cache de sessoes anteriores
      .then((res) => res.json())
      .then((data) =>
        setCertificados(
          data.map((c: any) => ({
            ...c,
            cod_certificado: Number(c.cod_certificado),
            status: Number(c.status ?? 0),
          }))
        )
      )
      .catch((err) => console.error("Erro ao carregar os certificados: ", err));
  }, []);




  const handleSendAll = async () => {
    try {
      for (const c of certificados) {
        await handleSendOne(c.cod_certificado);

        await new Promise(r => setTimeout(r, 200));
      }
      console.log("Envio para todos concluido");
    } catch (err) {
      console.error("Erro no envio para todos: ", err);
    }
  };

  const handleSendOne = async (cod_certificado: number) => {
    const res = await fetch("http://192.168.1.9/SICAD/enviar_certificado.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ cod_certificado }),
    });

    const raw = await res.text();
    console.log("HTTP:", res.status);
    console.log("RAW:", raw);

    let data: any;
    try { data = JSON.parse(raw); } catch { return; }

    if (data.success) {
      setCertificados(prev =>
        prev.map(c => c.cod_certificado === cod_certificado ? { ...c, status: 1 } : c)
      );
    } else {
      alert(data.message);
    }
  };



  return (
    <ScrollView className="flex-1 dark:bg-black">
      <Mainframe name="SICAD - Evento de Teste " photoUrl="evento.png" link="www.evento.com">
        <View className="px-8">
          <Text className="text-2xl dark:color-white">Certificados</Text>
          <View className="flex-row items-center justify-between my-2">
            <View className="flex-row items-center gap-2 mt-2">
              <Pressable
                onPress={() => router.push("./page")} // <- chama a rota como função
                className="w-min px-3 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"
              >
                <Ionicons name="add" size={22} />
                <Text>Criar</Text>
              </Pressable>

              <Pressable
                onPress={() => router.push("./send")}
                className="w-min px-3 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"
              >
                <Ionicons name="mail-outline" size={22} />
                <Text className="text-nowrap">Envio por E-mail</Text>
              </Pressable>

              <Pressable
                onPress={() => router.push("./settings")}
                className="w-min px-3 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"
              >
                <Ionicons name="settings-outline" size={22} />
                <Text>Configurações</Text>
              </Pressable>
            </View>
          </View>
          <View className="flex-row items-center justify-between mt-4 border-b-2 border-slate-300 pb-2">
            <Text className="text-2xl dark:color-white">Enviar Certificados</Text>
            <View className="flex-row mt-2 gap-2">
              <TextInput placeholder="Buscar" className="w-min bg-transparent border border-slate-400 rounded-lg px-2 py-1 color-slate-500 dark:color-white" />
              <Pressable className="w-min bg-[#2192ff] items-center justify-center rounded-lg px-2 py-1 color-white">Exportar</Pressable>
              <Pressable onPress={handleSendAll} className="w-min bg-[#2192ff] items-center justify-center rounded-lg px-2 py-1 color-white text-nowrap">Enviar para todos</Pressable>
            </View>
          </View>
          <View className='flex-row justify-center items-center border-b border-slate-300 px-4'>
            <Text className='w-5/12 font-semibold color-slate-400'>PARTICIPANTE</Text>
            <Text className='w-4/12 font-semibold color-slate-400'>E-MAIL</Text>
            <Text className='w-2/12 font-semibold color-slate-400'>STATUS</Text>
            <Text className='w-2/12 font-semibold color-slate-400'>OPÇÕES</Text>
          </View>
          <View>
            {certificados.map((certificado) => {
              console.log("ID CERT:", certificado.cod_certificado); // <-- TEM que aparecer número
              return (
                <ParticipantCertifyBox
                  key={certificado.cod_certificado}
                  participante={certificado.participante}
                  email={certificado.email}
                  status={certificado.status}
                  onSend={() => handleSendOne(certificado.cod_certificado)}
                />
              );
            })}

          </View>
        </View>
      </Mainframe>
    </ScrollView>
  );
}

