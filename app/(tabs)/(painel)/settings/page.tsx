import * as React from 'react';
import { Text, View, ScrollView, Pressable, TextInput } from 'react-native';

export default function Settings() {
  const [local, setLocal] = React.useState<'presencial' | 'online' | 'hibrido'>('presencial');

  const options = [
    { label: 'Presencial', value: 'presencial' },
    { label: 'Online', value: 'online' },
    { label: 'Híbrido', value: 'hibrido' },
  ];

  return (
    <ScrollView className="flex-1 dark:bg-[#121212] ">
        <View className="bg-white dark:bg-[#242424] p-4 pb-20 items-center justify-center rounded-md">
            <View className="w-4/12">
                {/* Informações Básicas */}
                <View className="border-b-2 border-slate-300 pb-4 mb-4">
                    <View>
                    <Text className="font-bold text-2xl">Informações Basicas</Text>
                    <Text>Dê um nome ao seu evento, faça ele se destacar e o torne unico</Text>
                    </View> <View className="mt-4">
                        <Text>Titulo do Evento</Text>
                        <TextInput className="mb-2 w-full bg-transparent border border-slate-400 rounded-lg px-2 py-1 color-slate-500 dark:color-white"/>
                        <Text>Link de Acesso</Text>
                        <TextInput className="mb-2 w-full bg-transparent border border-slate-400 rounded-lg px-2 py-1 color-slate-500 dark:color-white"/>
                        <View className="flex-row gap-8">
                            <View className="w-6/12">
                                <Text>Idiona do Evento</Text>
                                <TextInput className="w-full bg-transparent border border-slate-400 rounded-lg px-2 py-1 color-slate-500 dark:color-white"/>
                                <Text>As mensagens da plataforma serão enviado neste idioma</Text>
                            </View>
                            <View className="w-5/12">
                                <Text>Carga Horario Total</Text>
                                <TextInput className="mb-2 w-full bg-transparent border border-slate-400 rounded-lg px-2 py-1 color-slate-500 dark:color-white"/>
                            </View>
                        </View>
                    </View>
                </View>
                
                

                {/* Local */}
                <View className="border-b-2 border-slate-300 pb-4 mb-4">
                    <Text className="font-bold text-2xl">Local</Text>
                    <Text>Ajude as pessoas a descobrirem seu evento e informe os participantes onde comparecer</Text>
                    
                    <View className="mt-4 flex-row gap-4">
                        {options.map((option) => (
                            <Pressable
                            key={option.value}
                            className="flex-row items-center mb-2"
                            onPress={() => setLocal(option.value)}
                            >
                            <View
                                className={`w-5 h-5 mr-2 rounded-full border-2 ${
                                local === option.value ? 'border-blue-500 bg-blue-500' : 'border-gray-400'
                                }`}
                            />
                            <Text>{option.label}</Text>
                            </Pressable>
                        ))}
                    </View>
                    <View className="mt-4">
                        <Text>Local</Text>
                        <TextInput className="mb-2 w-full bg-transparent border border-slate-400 rounded-lg px-2 py-1 color-slate-500 dark:color-white"/>
                    </View>
                </View>
            </View>
        </View>
    </ScrollView>
  );
}
